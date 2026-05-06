const ModbusRTU = require("modbus-serial");

class ModbusReader {
  constructor(config) {
    this.config = config;
    this.client = new ModbusRTU();
    this.isConnected = false;
    this.retryCount = 0;
  }

  async connect() {
    try {
      await this.client.connectTCP(this.config.host, {
        port: this.config.port,
      });
      this.client.setID(this.config.unitId);
      this.client.setTimeout(this.config.timeout);
      this.isConnected = true;
      this.retryCount = 0;
      console.log(`[MODBUS] Baglanti kuruldu: ${this.config.host}:${this.config.port} Unit:${this.config.unitId}`);
      return true;
    } catch (err) {
      this.isConnected = false;
      console.error(`[MODBUS] Baglanti hatasi: ${err.message}`);
      return false;
    }
  }

  async reconnect() {
    if (this.retryCount >= this.config.maxRetries) {
      console.error(`[MODBUS] Maksimum yeniden deneme sayisina ulasildi (${this.config.maxRetries})`);
      return false;
    }
    this.retryCount++;
    console.log(`[MODBUS] Yeniden baglanma denemesi ${this.retryCount}/${this.config.maxRetries}...`);
    await this.sleep(this.config.reconnectInterval);
    return this.connect();
  }

  async readHoldingRegisters(address, count) {
    if (!this.isConnected) {
      throw new Error("Modbus baglantisi yok");
    }
    try {
      const result = await this.client.readHoldingRegisters(address, count);
      return result.data;
    } catch (err) {
      this.isConnected = false;
      throw err;
    }
  }

  async readInputRegisters(address, count) {
    if (!this.isConnected) {
      throw new Error("Modbus baglantisi yok");
    }
    try {
      const result = await this.client.readInputRegisters(address, count);
      return result.data;
    } catch (err) {
      this.isConnected = false;
      throw err;
    }
  }

  async readFloat32(address) {
    const data = await this.readHoldingRegisters(address, 2);
    return this.registersToFloat(data[0], data[1]);
  }

  async readInt32(address) {
    const data = await this.readHoldingRegisters(address, 2);
    return (data[0] << 16) | data[1];
  }

  async readUint16(address) {
    const data = await this.readHoldingRegisters(address, 1);
    return data[0];
  }

  registersToFloat(high, low) {
    const buf = Buffer.alloc(4);
    buf.writeUInt16BE(high, 0);
    buf.writeUInt16BE(low, 2);
    return buf.readFloatBE(0);
  }

  async disconnect() {
    if (this.client && this.isConnected) {
      try {
        this.client.close();
      } catch (e) {}
      this.isConnected = false;
      console.log("[MODBUS] Baglanti kapatildi");
    }
  }

  sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}

module.exports = ModbusReader;
