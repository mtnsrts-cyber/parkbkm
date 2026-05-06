const ModbusRTU = require("modbus-serial");

const HOST = "192.168.201.248";
const PORT = 502;
const UNIT_ID = 5;

async function scan() {
  const client = new ModbusRTU();

  try {
    await client.connectTCP(HOST, { port: PORT });
    client.setID(UNIT_ID);
    client.setTimeout(3000);
    console.log(`Baglandi: ${HOST}:${PORT} Unit:${UNIT_ID}\n`);

    console.log("=== HOLDING REGISTERS (FC03) Tarama ===");
    for (let addr = 0; addr <= 120; addr += 10) {
      try {
        const result = await client.readHoldingRegisters(addr, 10);
        const values = result.data;
        values.forEach((val, i) => {
          const regAddr = addr + i;
          if (val !== 0) {
            console.log(`  Reg ${regAddr} (4${String(regAddr + 1).padStart(4, "0")}): ${val} (0x${val.toString(16).toUpperCase()})`);
          }
        });
      } catch (err) {
        console.log(`  Reg ${addr}-${addr + 9}: HATA - ${err.message}`);
      }
    }

    console.log("\n=== INPUT REGISTERS (FC04) Tarama ===");
    for (let addr = 0; addr <= 120; addr += 10) {
      try {
        const result = await client.readInputRegisters(addr, 10);
        const values = result.data;
        values.forEach((val, i) => {
          const regAddr = addr + i;
          if (val !== 0) {
            console.log(`  Reg ${regAddr} (3${String(regAddr + 1).padStart(4, "0")}): ${val} (0x${val.toString(16).toUpperCase()})`);
          }
        });
      } catch (err) {
        console.log(`  Reg ${addr}-${addr + 9}: HATA - ${err.message}`);
      }
    }

    console.log("\n=== DETAYLI HOLDING REGISTERS 0-50 ===");
    try {
      const result = await client.readHoldingRegisters(0, 50);
      result.data.forEach((val, i) => {
        console.log(`  Reg ${i} (4${String(i + 1).padStart(4, "0")}): ${val}`);
      });
    } catch (err) {
      console.log(`  HATA: ${err.message}`);
    }

    console.log("\n=== DETAYLI INPUT REGISTERS 0-50 ===");
    try {
      const result = await client.readInputRegisters(0, 50);
      result.data.forEach((val, i) => {
        console.log(`  Reg ${i} (3${String(i + 1).padStart(4, "0")}): ${val}`);
      });
    } catch (err) {
      console.log(`  HATA: ${err.message}`);
    }

  } catch (err) {
    console.error("Baglanti hatasi:", err.message);
  } finally {
    client.close();
  }
}

scan();
