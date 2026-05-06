const ModbusClient = require("./modbusClient");

const config = {
  host: "192.168.201.248",
  port: 502,
  unitId: 5,
  timeout: 10000,
  reconnectInterval: 3000,
  maxRetries: 3
};

const client = new ModbusClient(config);

async function testCurrent() {
  console.log("=== AKIM REGISTER TESTI ===\n");

  try {
    // 1. Akım (register 59, 60) uint32
    console.log("Register 59 (_uint16_ yani 4byte):");
    try {
      const r59 = await client.readUint16(59);
      console.log("  HR59 raw:", r59, "(0x" + r59.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR59 HATA:", e.message);
    }

    try {
      const r60 = await client.readUint16(60);
      console.log("  HR60 raw:", r60, "(0x" + r60.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR60 HATA:", e.message);
    }

    try {
      const hi = await client.readUint16(59);
      const lo = await client.readUint16(60);
      const val = ((hi << 16) | lo) >>> 0;
      console.log("  HR59-60 uint32:", val, "/100 =", (val/100).toFixed(2), "A");
    } catch (e) {
      console.log("  HR59-60 HATA:", e.message);
    }

    console.log("\nRegister 61 (L2 akım uint32):");
    try {
      const r61 = await client.readUint16(61);
      console.log("  HR61 raw:", r61, "(0x" + r61.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR61 HATA:", e.message);
    }

    try {
      const r62 = await client.readUint16(62);
      console.log("  HR62 raw:", r62, "(0x" + r62.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR62 HATA:", e.message);
    }

    console.log("\nRegister 63 (L3 akım uint32):");
    try {
      const r63 = await client.readUint16(63);
      console.log("  HR63 raw:", r63, "(0x" + r63.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR63 HATA:", e.message);
    }

    try {
      const r64 = await client.readUint16(64);
      console.log("  HR64 raw:", r64, "(0x" + r64.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR64 HATA:", e.message);
    }

    console.log("\n=== DIGER 32-BIT DEGERLERLE KARSILASTIRMA ===");

    console.log("\nRegister 24 (L1 aktif güç int32):");
    try {
      const r24 = await client.readInt16(24);
      console.log("  HR24 raw:", r24, "(0x" + (r24 & 0xFFFF).toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR24 HATA:", e.message);
    }
    try {
      const r25 = await client.readUint16(25);
      console.log("  HR25 raw:", r25, "(0x" + r25.toString(16).padStart(4, "0") + ")");
    } catch (e) {
      console.log("  HR25 HATA:", e.message);
    }

    console.log("\nRegister 56-58 (gerilim):");
    try {
      const r56 = await client.readUint16(56);
      console.log("  HR56 (L1 V):", r56, "V");
    } catch (e) {
      console.log("  HR56 HATA:", e.message);
    }
    try {
      const r57 = await client.readUint16(57);
      console.log("  HR57 (L2 V):", r57, "V");
    } catch (e) {
      console.log("  HR57 HATA:", e.message);
    }
    try {
      const r58 = await client.readUint16(58);
      console.log("  HR58 (L3 V):", r58, "V");
    } catch (e) {
      console.log("  HR58 HATA:", e.message);
    }

    console.log("\nRegister 42-44 (Cos Phi int16):");
    try {
      const r42 = await client.readInt16(42);
      console.log("  HR42 (Cos L1):", r42, "/100 =", (r42/100).toFixed(2));
    } catch (e) {
      console.log("  HR42 HATA:", e.message);
    }

  } catch (err) {
    console.error("Genel hata:", err.message);
  } finally {
    client.disconnect();
  }
}

testCurrent();
