const http = require("http");
const fs = require("fs");
const path = require("path");
const crypto = require("crypto");
const ModbusClient = require("./modbusClient");
const config = require("./config.json");

const PORT = 3456;

const client = new ModbusClient(config.modbus);
let clients = [];

const mimeTypes = {
  ".html": "text/html",
  ".js": "application/javascript",
  ".css": "text/css",
  ".json": "application/json",
};

function computeAcceptKey(key) {
  return crypto
    .createHash("sha1")
    .update(key + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")
    .digest("base64");
}

function serveStatic(req, res) {
  let filePath = path.join(__dirname, "web", req.url === "/" ? "index.html" : req.url);
  const ext = path.extname(filePath).toLowerCase();
  const contentType = mimeTypes[ext] || "application/octet-stream";

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404);
      res.end("Not found");
      return;
    }
    res.writeHead(200, { "Content-Type": contentType });
    res.end(data);
  });
}

const server = http.createServer(serveStatic);

server.on("upgrade", (request, socket, head) => {
  if (request.url === "/ws") {
    const wsKey = request.headers["sec-websocket-key"];
    const acceptKey = computeAcceptKey(wsKey);
    socket.write(
      "HTTP/1.1 101 Switching Protocols\r\n" +
        "Upgrade: websocket\r\n" +
        "Connection: Upgrade\r\n" +
        "Sec-WebSocket-Accept: " + acceptKey + "\r\n\r\n"
    );

    clients.push(socket);

    socket.on("close", () => {
      clients = clients.filter((c) => c !== socket);
    });

    socket.on("error", () => {
      clients = clients.filter((c) => c !== socket);
    });
  }
});

function sendJson(ws, data) {
  try {
    const json = JSON.stringify(data);
    const payload = Buffer.from(json, "utf8");
    const opcode = 0x81;
    let frame;

    if (payload.length <= 125) {
      frame = Buffer.alloc(2 + payload.length);
      frame[0] = opcode;
      frame[1] = payload.length;
      payload.copy(frame, 2);
    } else if (payload.length <= 65535) {
      frame = Buffer.alloc(4 + payload.length);
      frame[0] = opcode;
      frame[1] = 126;
      frame.writeUInt16BE(payload.length, 2);
      payload.copy(frame, 4);
    } else {
      frame = Buffer.alloc(10 + payload.length);
      frame[0] = opcode;
      frame[1] = 127;
      frame.writeBigUInt64BE(BigInt(payload.length), 2);
      payload.copy(frame, 10);
    }

    ws.write(frame);
  } catch (e) {
    console.log("[HATA] WebSocket gonderim:", e.message);
  }
}

function broadcast(data) {
  clients.forEach((c) => {
    if (!c.destroyed) {
      sendJson(c, data);
    }
  });
}

async function readGroup(group) {
  const result = {};
  for (const key in group) {
    try {
      const reg = group[key];
      let raw = null;
      if (reg.type === "uint32") raw = await client.readUint32(reg.address);
      else if (reg.type === "int32") raw = await client.readInt32(reg.address);
      else if (reg.type === "int16") raw = await client.readInt16(reg.address);
      else raw = await client.readUint16(reg.address);
      result[key] = raw !== null ? raw / reg.multiplier : null;
    } catch {
      result[key] = null;
    }
  }
  return result;
}

function toKw(val) {
  return val !== null ? parseFloat((val / 1000).toFixed(2)) : null;
}

function toKwh(val) {
  return val !== null ? parseFloat((val / 1000).toFixed(2)) : null;
}

async function tick() {
  const r = config.registers;
  const d = { timestamp: new Date().toISOString() };

  d.voltage = await readGroup(r.voltage);
  d.current = await readGroup(r.current);
  d.frequency = await readGroup(r.frequency);
  d.cos_phi = await readGroup(r.cos_phi);
  d.thdi = await readGroup(r.thdi);

  const ap = await readGroup(r.active_power);
  d.active_power = {
    ...ap,
    Total: ap.L1 !== null && ap.L2 !== null && ap.L3 !== null ? toKw(ap.L1 + ap.L2 + ap.L3) : null,
    L1: toKw(ap.L1), L2: toKw(ap.L2), L3: toKw(ap.L3),
  };

  const ip = await readGroup(r.inductive_power);
  d.inductive_power = {
    ...ip,
    Total: ip.L1 !== null && ip.L2 !== null && ip.L3 !== null ? toKw(ip.L1 + ip.L2 + ip.L3) : null,
    L1: toKw(ip.L1), L2: toKw(ip.L2), L3: toKw(ip.L3),
  };

  const cp = await readGroup(r.capacitive_power);
  d.capacitive_power = {
    ...cp,
    Total: cp.L1 !== null && cp.L2 !== null && cp.L3 !== null ? toKw(cp.L1 + cp.L2 + cp.L3) : null,
    L1: toKw(cp.L1), L2: toKw(cp.L2), L3: toKw(cp.L3),
  };

  const ae = await readGroup(r.active_energy_consumption);
  d.active_energy = {
    Total: ae.L1 !== null && ae.L2 !== null && ae.L3 !== null ? toKwh(ae.L1 + ae.L2 + ae.L3) : null,
    L1: toKwh(ae.L1), L2: toKwh(ae.L2), L3: toKwh(ae.L3),
  };

  const ie = await readGroup(r.inductive_energy);
  const ce = await readGroup(r.capacitive_energy);
  d.reactive_energy = {
    inductive_total: ie.L1 !== null && ie.L2 !== null && ie.L3 !== null ? toKwh(ie.L1 + ie.L2 + ie.L3) : null,
    capacitive_total: ce.L1 !== null && ce.L2 !== null && ce.L3 !== null ? toKwh(ce.L1 + ce.L2 + ce.L3) : null,
  };

  broadcast(d);
}

async function loop() {
  while (true) {
    try { await tick(); } catch (e) {}
    await new Promise((r) => setTimeout(r, config.updateInterval || 3000));
  }
}

server.listen(PORT, () => {
  console.log(`Web sunucusu: http://localhost:${PORT}`);
  loop();
});
