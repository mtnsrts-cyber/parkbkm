const ModbusClient = require("./modbusClient");
const Dashboard = require("./dashboard");
const config = require("./config.json");

class SmartSOG5Monitor {
  constructor() {
    this.client = new ModbusClient(config.modbus);
    this.dashboard = new Dashboard();
    this.running = false;
    this.intervalId = null;
  }

  async start() {
    console.log("[SISTEM] Smart SOG5 SGA124 Monitor baslatiliyor...");
    console.log(`[SISTEM] Hedef: ${config.modbus.host}:${config.modbus.port} Unit:${config.modbus.unitId}`);
    this.running = true;
    this.dashboard.render();
    this.intervalId = setInterval(() => this.tick(), config.updateInterval);
    process.on("SIGINT", () => this.stop());
    process.on("SIGTERM", () => this.stop());
  }

  async tick() {
    if (!this.running) return;
    try {
      const data = await this.readAllData();
      this.dashboard.update(data);
    } catch (err) {
      console.error(`[HATA] ${err.message}`);
    }
  }

  async readRegister(regDef) {
    try {
      let rawValue;
      if (regDef.type === "uint32") {
        rawValue = await this.client.readUint32(regDef.address);
      } else if (regDef.type === "int32") {
        rawValue = await this.client.readInt32(regDef.address);
      } else if (regDef.type === "int16") {
        rawValue = await this.client.readInt16(regDef.address);
      } else {
        rawValue = await this.client.readUint16(regDef.address);
      }
      return rawValue / regDef.multiplier;
    } catch (err) {
      return null;
    }
  }

  async readAllData() {
    const reg = config.registers;
    const data = {};

    data.voltage = {
      L1: await this.readRegister(reg.voltage.l1),
      L2: await this.readRegister(reg.voltage.l2),
      L3: await this.readRegister(reg.voltage.l3),
    };
    data.voltage["L1-L2"] = this.calcLineVoltage(data.voltage.L1, data.voltage.L2);
    data.voltage["L2-L3"] = this.calcLineVoltage(data.voltage.L2, data.voltage.L3);
    data.voltage["L1-L3"] = this.calcLineVoltage(data.voltage.L1, data.voltage.L3);

    data.active_power = {
      L1: await this.readRegister(reg.active_power.l1),
      L2: await this.readRegister(reg.active_power.l2),
      L3: await this.readRegister(reg.active_power.l3),
    };
    if (data.active_power.L1 !== null && data.active_power.L2 !== null && data.active_power.L3 !== null) {
      data.active_power.Total = data.active_power.L1 + data.active_power.L2 + data.active_power.L3;
    }

    data.inductive_power = {
      L1: await this.readRegister(reg.inductive_power.l1),
      L2: await this.readRegister(reg.inductive_power.l2),
      L3: await this.readRegister(reg.inductive_power.l3),
    };
    if (data.inductive_power.L1 !== null && data.inductive_power.L2 !== null && data.inductive_power.L3 !== null) {
      data.inductive_power.Total = data.inductive_power.L1 + data.inductive_power.L2 + data.inductive_power.L3;
    }

    data.capacitive_power = {
      L1: await this.readRegister(reg.capacitive_power.l1),
      L2: await this.readRegister(reg.capacitive_power.l2),
      L3: await this.readRegister(reg.capacitive_power.l3),
    };
    if (data.capacitive_power.L1 !== null && data.capacitive_power.L2 !== null && data.capacitive_power.L3 !== null) {
      data.capacitive_power.Total = data.capacitive_power.L1 + data.capacitive_power.L2 + data.capacitive_power.L3;
    }

    data.active_energy_consumption = {
      L1: await this.readRegister(reg.active_energy_consumption.l1),
      L2: await this.readRegister(reg.active_energy_consumption.l2),
      L3: await this.readRegister(reg.active_energy_consumption.l3),
    };
    if (data.active_energy_consumption.L1 !== null && data.active_energy_consumption.L2 !== null && data.active_energy_consumption.L3 !== null) {
      data.active_energy_consumption.Total = data.active_energy_consumption.L1 + data.active_energy_consumption.L2 + data.active_energy_consumption.L3;
    }

    data.inductive_energy = {
      L1: await this.readRegister(reg.inductive_energy.l1),
      L2: await this.readRegister(reg.inductive_energy.l2),
      L3: await this.readRegister(reg.inductive_energy.l3),
    };

    data.capacitive_energy = {
      L1: await this.readRegister(reg.capacitive_energy.l1),
      L2: await this.readRegister(reg.capacitive_energy.l2),
      L3: await this.readRegister(reg.capacitive_energy.l3),
    };

    data.current = {
      L1: await this.readRegister(reg.current.l1),
      L2: await this.readRegister(reg.current.l2),
      L3: await this.readRegister(reg.current.l3),
    };

    data.cos_phi = {
      L1: await this.readRegister(reg.cos_phi.l1),
      L2: await this.readRegister(reg.cos_phi.l2),
      L3: await this.readRegister(reg.cos_phi.l3),
    };

    data.frequency = {
      L1: await this.readRegister(reg.frequency.l1),
      L2: await this.readRegister(reg.frequency.l2),
      L3: await this.readRegister(reg.frequency.l3),
    };

    data.thdi = {
      L1: await this.readRegister(reg.thdi.l1),
      L2: await this.readRegister(reg.thdi.l2),
      L3: await this.readRegister(reg.thdi.l3),
    };

    data.timestamp = new Date().toISOString();
    return data;
  }

  calcLineVoltage(v1, v2) {
    if (v1 === null || v2 === null) return null;
    return parseFloat((Math.sqrt(v1 * v1 + v2 * v2 + v1 * v2)).toFixed(1));
  }

  async stop() {
    console.log("\n[SISTEM] Durduruluyor...");
    this.running = false;
    if (this.intervalId) clearInterval(this.intervalId);
    process.exit(0);
  }
}

const monitor = new SmartSOG5Monitor();
monitor.start().catch((err) => {
  console.error("[SISTEM] Kritik hata:", err.message);
  process.exit(1);
});
