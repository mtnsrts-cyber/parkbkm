const net = require("net");

const HOST = "192.168.201.248";
const PORT = 502;
const UNIT_ID = 5;

let transactionId = 1;

function sendModbus(fc, startAddr, count) {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    socket.setTimeout(5000);
    const txId = transactionId++;
    const buf = Buffer.alloc(12);
    buf.writeUInt16BE(txId, 0);
    buf.writeUInt16BE(0, 2);
    buf.writeUInt16BE(6, 4);
    buf.writeUInt8(UNIT_ID, 6);
    buf.writeUInt8(fc, 7);
    buf.writeUInt16BE(startAddr, 8);
    buf.writeUInt16BE(count, 10);

    socket.connect(PORT, HOST, () => socket.write(buf));

    const chunks = [];
    socket.on("data", (chunk) => {
      chunks.push(chunk);
      const total = Buffer.concat(chunks);
      if (total.length >= 9) {
        const len = total.readUInt16BE(4);
        if (total.length >= len + 6) {
          socket.destroy();
          const data = total;
          const rxTxId = data.readUInt16BE(0);
          const rfc = data.readUInt8(7);
          if (rfc & 0x80) {
            resolve({ error: `Exception ${data.readUInt8(8)}` });
            return;
          }
          const byteCount = data.readUInt8(8);
          const values = [];
          for (let i = 0; i < byteCount; i += 2) {
            if (9 + i + 1 < data.length) {
              values.push(data.readUInt16BE(9 + i));
            }
          }
          resolve({ values, byteCount, rawLen: data.length });
        }
      }
    });
    socket.on("timeout", () => { socket.destroy(); reject(new Error("Timeout")); });
    socket.on("error", (err) => { socket.destroy(); reject(err); });
  });
}

function toFloat32(hi, lo) {
  const buf = Buffer.alloc(4);
  buf.writeUInt16BE(hi, 0);
  buf.writeUInt16BE(lo, 2);
  return buf.readFloatBE(0);
}

function toFloat32SW(hi, lo) {
  const buf = Buffer.alloc(4);
  buf.writeUInt16BE(lo, 0);
  buf.writeUInt16BE(hi, 2);
  return buf.readFloatBE(0);
}

function toInt32(hi, lo) {
  return ((hi << 16) | lo) >>> 0;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function scan() {
  console.log("=== SMART SOG5 - 32-BIT REGISTER ANALIZI ===\n");

  console.log("--- 32-bit ciftler (0-40) - TEK SEFERDE OKUMA ---");
  for (let addr = 0; addr <= 40; addr += 2) {
    try {
      const result = await sendModbus(0x03, addr, 2);
      if (result.error) {
        console.log(`  HR ${addr}-${addr + 1}: ${result.error}`);
        continue;
      }
      if (result.values && result.values.length >= 2) {
        const [hi, lo] = result.values;
        const fBE = toFloat32(hi, lo);
        const fSW = toFloat32SW(hi, lo);
        const i32 = toInt32(hi, lo);
        const i32s = (hi << 16) | lo;
        console.log(`  HR ${String(addr).padStart(2)}-${String(addr + 1).padStart(2)}: raw=[${hi}, ${lo}] | floatBE=${fBE.toFixed(2)} | floatSW=${fSW.toFixed(2)} | uint32=${i32} | int32=${i32s}`);
      }
    } catch (err) {
      console.log(`  HR ${addr}-${addr + 1}: HATA - ${err.message}`);
    }
    await sleep(200);
  }

  console.log("\n--- YAPILANDIRMA REGISTER'LARI (41-77) ---");
  for (let addr = 41; addr <= 77; addr++) {
    try {
      const result = await sendModbus(0x03, addr, 1);
      if (result.error) {
        console.log(`  HR ${addr}: ${result.error}`);
        continue;
      }
      if (result.values && result.values.length > 0) {
        console.log(`  HR ${String(addr).padStart(2)} (4${String(addr + 1).padStart(4, "0")}): ${result.values[0]} bytes=${result.byteCount}`);
      }
    } catch (err) {
      console.log(`  HR ${addr}: HATA - ${err.message}`);
    }
    await sleep(100);
  }

  console.log("\n--- BUYUK BLOK OKUMA (0-40, 20'li) ---");
  try {
    const result = await sendModbus(0x03, 0, 20);
    if (result.values) {
      console.log(`  byteCount=${result.byteCount} values.length=${result.values.length}`);
      for (let i = 0; i < result.values.length; i += 2) {
        if (i + 1 < result.values.length) {
          const [hi, lo] = [result.values[i], result.values[i + 1]];
          const fBE = toFloat32(hi, lo);
          const fSW = toFloat32SW(hi, lo);
          const i32 = toInt32(hi, lo);
          console.log(`  HR ${String(i).padStart(2)}-${String(i + 1).padStart(2)}: raw=[${hi}, ${lo}] | floatBE=${fBE.toFixed(2)} | floatSW=${fSW.toFixed(2)} | uint32=${i32}`);
        }
      }
    }
  } catch (err) {
    console.log(`  HATA: ${err.message}`);
  }
  await sleep(500);

  try {
    const result = await sendModbus(0x03, 20, 20);
    if (result.values) {
      console.log(`  byteCount=${result.byteCount} values.length=${result.values.length}`);
      for (let i = 0; i < result.values.length; i += 2) {
        if (i + 1 < result.values.length) {
          const [hi, lo] = [result.values[i], result.values[i + 1]];
          const fBE = toFloat32(hi, lo);
          const fSW = toFloat32SW(hi, lo);
          const i32 = toInt32(hi, lo);
          console.log(`  HR ${String(20 + i).padStart(2)}-${String(20 + i + 1).padStart(2)}: raw=[${hi}, ${lo}] | floatBE=${fBE.toFixed(2)} | floatSW=${fSW.toFixed(2)} | uint32=${i32}`);
        }
      }
    }
  } catch (err) {
    console.log(`  HATA: ${err.message}`);
  }
}

scan();
