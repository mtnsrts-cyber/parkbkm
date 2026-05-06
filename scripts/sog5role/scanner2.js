const ModbusRTU = require("modbus-serial");

const HOST = "192.168.201.248";
const PORT = 502;
const UNIT_ID = 5;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function scan() {
  const client = new ModbusRTU();

  try {
    await client.connectTCP(HOST, { port: PORT });
    client.setID(UNIT_ID);
    client.setTimeout(3000);
    console.log(`Baglandi: ${HOST}:${PORT} Unit:${UNIT_ID}\n`);

    console.log("=== TEK TEK HOLDING REGISTER OKUMA (0-79) ===");
    for (let addr = 0; addr < 80; addr++) {
      try {
        const result = await client.readHoldingRegisters(addr, 1);
        const val = result.data[0];
        console.log(`  HR ${addr} (4${String(addr + 1).padStart(4, "0")}): ${val} (0x${val.toString(16).toUpperCase().padStart(4, "0")}) raw=${val}`);
      } catch (err) {
        console.log(`  HR ${addr}: HATA - ${err.message}`);
      }
      await sleep(100);
    }

    console.log("\n=== 2 REGISTERS OKUMA - FLOAT32 DENEME (0-40) ===");
    for (let addr = 0; addr < 40; addr += 2) {
      try {
        const result = await client.readHoldingRegisters(addr, 2);
        const buf = Buffer.alloc(4);
        buf.writeUInt16BE(result.data[0], 0);
        buf.writeUInt16BE(result.data[1], 2);
        const floatVal = buf.readFloatBE(0);
        const intVal = (result.data[0] << 16) | result.data[1];
        console.log(`  HR ${addr}-${addr + 1}: raw=[${result.data[0]}, ${result.data[1]}] float=${floatVal.toFixed(2)} int32=${intVal}`);
      } catch (err) {
        console.log(`  HR ${addr}-${addr + 1}: HATA - ${err.message}`);
      }
      await sleep(100);
    }

  } catch (err) {
    console.error("Baglanti hatasi:", err.message);
  } finally {
    client.close();
  }
}

scan();
