class DataProcessor {
  constructor(registerConfig) {
    this.registerConfig = registerConfig;
  }

  process(rawData) {
    return {
      timestamp: new Date().toISOString(),
      voltage: this.processVoltage(rawData.voltage),
      power: this.processPower(rawData.power),
      energy: this.processEnergy(rawData.energy),
      reactive: this.processReactive(rawData.reactive),
    };
  }

  processVoltage(data) {
    if (!data) return null;
    const cfg = this.registerConfig.voltage;
    return {
      "L1-L2": this.scale(data.l1_l2, cfg.l1_l2.scale),
      "L2-L3": this.scale(data.l2_l3, cfg.l2_l3.scale),
      "L1-L3": this.scale(data.l1_l3, cfg.l1_l3.scale),
    };
  }

  processPower(data) {
    if (!data) return null;
    const cfg = this.registerConfig.power;
    return {
      L1: this.scale(data.l1_active, cfg.l1_active.scale),
      L2: this.scale(data.l2_active, cfg.l2_active.scale),
      L3: this.scale(data.l3_active, cfg.l3_active.scale),
      Toplam: this.scale(data.total_active, cfg.total_active.scale),
    };
  }

  processEnergy(data) {
    if (!data) return null;
    const cfg = this.registerConfig.energy;
    return {
      "Toplam kWh": this.scale(data.total_consumption, cfg.total_consumption.scale),
    };
  }

  processReactive(data) {
    if (!data) return null;
    const cfg = this.registerConfig.reactive_power;
    return {
      "Reaktif kVAr": this.scale(data.total_reactive, cfg.total_reactive.scale),
      "Kapasitif kVAr": data.capacitive !== undefined ? this.scale(data.capacitive, cfg.capacitive ? cfg.capacitive.scale : 0.1) : null,
    };
  }

  scale(value, factor) {
    if (value === null || value === undefined) return null;
    return parseFloat((value * factor).toFixed(2));
  }

  formatValue(value, unit) {
    if (value === null || value === undefined) return "---";
    return `${value} ${unit}`;
  }
}

module.exports = DataProcessor;
