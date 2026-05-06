const net = require("net");

class ModbusClient {
  constructor(config) {
    this.config = config;
    this.transactionId = 1;
    this.socket = null;
    this.isConnected = false;
    this.requestQueue = [];
    this.currentRequest = null;
  }

  async connect() {
    return new Promise((resolve, reject) => {
      if (this.isConnected && this.socket) {
        resolve();
        return;
      }

      this.socket = new net.Socket();
      this.socket.setTimeout(10000);

      this.socket.connect(this.config.port, this.config.host, () => {
        this.isConnected = true;
        console.log(`[MODBUS] Baglandi: ${this.config.host}:${this.config.port}`);
        resolve();
      });

      this.socket.on("data", (data) => {
        if (this.currentRequest) {
          this.currentRequest.resolve(data);
          this.currentRequest = null;
          this.processQueue();
        }
      });

      this.socket.on("error", (err) => {
        this.isConnected = false;
        if (this.currentRequest) {
          this.currentRequest.reject(err);
          this.currentRequest = null;
        }
        this.socket = null;
      });

      this.socket.on("close", () => {
        this.isConnected = false;
        this.socket = null;
      });

      this.socket.on("timeout", () => {
        if (this.currentRequest) {
          this.currentRequest.reject(new Error("Socket timeout"));
          this.currentRequest = null;
        }
        this.socket.destroy();
        this.isConnected = false;
        this.socket = null;
        this.processQueue();
      });
    });
  }

  async ensureConnected() {
    if (!this.isConnected || !this.socket) {
      await this.connect();
    }
  }

  processQueue() {
    if (this.currentRequest || !this.requestQueue.length) return;
    this.currentRequest = this.requestQueue.shift();
    try {
      this.socket.write(this.currentRequest.buffer);
    } catch (err) {
      this.currentRequest.reject(err);
      this.currentRequest = null;
      this.processQueue();
    }
  }

  sendRequest(fc, startAddr, count) {
    return new Promise(async (resolve, reject) => {
      try {
        await this.ensureConnected();

        const txId = this.transactionId++;
        const buf = Buffer.alloc(12);
        buf.writeUInt16BE(txId, 0);
        buf.writeUInt16BE(0, 2);
        buf.writeUInt16BE(6, 4);
        buf.writeUInt8(this.config.unitId, 6);
        buf.writeUInt8(fc, 7);
        buf.writeUInt16BE(startAddr, 8);
        buf.writeUInt16BE(count, 10);

        const timeout = setTimeout(() => {
          reject(new Error(`Timeout reading register ${startAddr}`));
        }, 5000);

        const req = {
          buffer: buf,
          resolve: (data) => {
            clearTimeout(timeout);
            const result = this.parseResponse(data);
            if (result.error) {
              reject(new Error(result.error));
            } else {
              resolve(result.values);
            }
          },
          reject: (err) => {
            clearTimeout(timeout);
            reject(err);
          },
        };

        this.requestQueue.push(req);
        this.processQueue();
      } catch (err) {
        reject(err);
      }
    });
  }

  parseResponse(data) {
    if (data.length < 9) return { error: "Response too short" };
    const fc = data.readUInt8(7);
    if (fc & 0x80) {
      const exc = data.readUInt8(8);
      return { error: `Exception ${exc}` };
    }
    const byteCount = data.readUInt8(8);
    const values = [];
    for (let i = 0; i < byteCount; i += 2) {
      if (9 + i + 1 < data.length) {
        values.push(data.readUInt16BE(9 + i));
      }
    }
    return { values };
  }

  async readUint16(address) {
    const values = await this.sendRequest(0x03, address, 1);
    return values[0];
  }

  async readInt16(address) {
    const values = await this.sendRequest(0x03, address, 1);
    let val = values[0];
    if (val >= 32768) val = val - 65536;
    return val;
  }

  async readUint32(address) {
    const hi = await this.readUint16(address);
    await this.sleep(50);
    const lo = await this.readUint16(address + 1);
    return ((hi << 16) | lo) >>> 0;
  }

  async readInt32(address) {
    const hi = await this.readInt16(address);
    await this.sleep(50);
    const lo = await this.readUint16(address + 1);
    return (hi << 16) | lo;
  }

  sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
  }

  disconnect() {
    if (this.socket) {
      this.socket.destroy();
      this.socket = null;
      this.isConnected = false;
    }
  }
}

module.exports = ModbusClient;
