const net = require("net");

const HOST = "192.168.201.248";
const PORT = 502;
const UNIT_ID = 5;

let transactionId = 1;

function buildModbusRequest(unitId, fc, startAddr, count) {
  const txId = transactionId++;
  const pduLen = 6;
  const buf = Buffer.alloc(12);
  buf.writeUInt16BE(txId, 0);
  buf.writeUInt16BE(0, 2);
  buf.writeUInt16BE(pduLen, 4);
  buf.writeUInt8(unitId, 6);
  buf.writeUInt8(fc, 7);
  buf.writeUInt16BE(startAddr, 8);
  buf.writeUInt16BE(count, 10);
  return { buffer: buf, txId };
}

function parseModbusResponse(data) {
  if (data.length < 9) return { error: "Response too short", raw: data };

  const txId = data.readUInt16BE(0);
  const protocolId = data.readUInt16BE(2);
  const length = data.readUInt16BE(4);
  const unitId = data.readUInt8(6);
  const fc = data.readUInt8(7);

  if (fc & 0x80) {
    const exceptionCode = data.readUInt8(8);
    return { txId, unitId, fc, error: `Exception ${exceptionCode}`, raw: data };
  }

  const byteCount = data.readUInt8(8);
  const values = [];
  for (let i = 0; i < byteCount; i += 2) {
    if (8 + 1 + i + 1 < data.length) {
      values.push(data.readUInt16BE(9 + i));
    }
  }

  return { txId, unitId, fc, byteCount, values, raw: data };
}

function sendModbusRequest(fc, startAddr, count) {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    socket.setTimeout(5000);

    const { buffer, txId } = buildModbusRequest(UNIT_ID, fc, startAddr, count);

    socket.connect(PORT, HOST, () => {
      socket.write(buffer);
    });

    const chunks = [];
    socket.on("data", (chunk) => {
      chunks.push(chunk);
      const totalLen = Buffer.concat(chunks).length;
      if (totalLen >= 9) {
        const headerLen = Buffer.concat(chunks).readUInt16BE(4);
        if (totalLen >= headerLen + 6) {
          socket.destroy();
          const data = Buffer.concat(chunks);
          resolve(parseModbusResponse(data));
        }
      }
    });

    socket.on("timeout", () => {
      socket.destroy();
      reject(new Error("Timeout"));
    });

    socket.on("error", (err) => {
      socket.destroy();
      reject(err);
    });
  });
}

async function scan() {
  console.log(`Baglaniliyor: ${HOST}:${PORT} Unit:${UNIT_ID}\n`);

  console.log("=== HOLDING REGISTERS (FC03) - TEK TEK OKUMA ===");
  for (let addr = 0; addr <= 80; addr++) {
    try {
      const result = await sendModbusRequest(0x03, addr, 1);
      if (result.error) {
        console.log(`  HR ${addr} (4${String(addr + 1).padStart(4, "0")}): HATA - ${result.error}`);
      } else if (result.values && result.values.length > 0) {
        const val = result.values[0];
        console.log(`  HR ${addr} (4${String(addr + 1).padStart(4, "0")}): ${val} (0x${val.toString(16).toUpperCase().padStart(4, "0")}) byteCount=${result.byteCount} rawLen=${result.raw.length}`);
      }
    } catch (err) {
      console.log(`  HR ${addr}: BAGLANTI HATASI - ${err.message}`);
    }
  }

  console.log("\n=== HOLDING REGISTERS (FC03) - 2'LI OKUMA (FLOAT32) ===");
  for (let addr = 0; addr <= 80; addr += 2) {
    try {
      const result = await sendModbusRequest(0x03, addr, 2);
      if (result.error) {
        console.log(`  HR ${addr}-${addr + 1}: HATA - ${result.error}`);
      } else if (result.values && result.values.length >= 2) {
        const buf = Buffer.alloc(4);
        buf.writeUInt16BE(result.values[0], 0);
        buf.writeUInt16BE(result.values[1], 2);
        const floatBE = buf.readFloatBE(0);
        const floatLE = buf.readFloatLE(0);
        const int32 = (result.values[0] << 16) | result.values[1];
        console.log(`  HR ${addr}-${addr + 1}: raw=[${result.values[0]}, ${result.values[1]}] floatBE=${floatBE.toFixed(4)} floatLE=${floatLE.toFixed(4)} int32=${int32}`);
      }
    } catch (err) {
      console.log(`  HR ${addr}-${addr + 1}: BAGLANTI HATASI - ${err.message}`);
    }
  }

  console.log("\n=== INPUT REGISTERS (FC04) - TEK TEK OKUMA ===");
  for (let addr = 0; addr <= 80; addr++) {
    try {
      const result = await sendModbusRequest(0x04, addr, 1);
      if (result.error) {
        console.log(`  IR ${addr} (3${String(addr + 1).padStart(4, "0")}): HATA - ${result.error}`);
      } else if (result.values && result.values.length > 0) {
        const val = result.values[0];
        console.log(`  IR ${addr} (3${String(addr + 1).padStart(4, "0")}): ${val} (0x${val.toString(16).toUpperCase().padStart(4, "0")})`);
      }
    } catch (err) {
      console.log(`  IR ${addr}: BAGLANTI HATASI - ${err.message}`);
    }
  }
}

scan();
