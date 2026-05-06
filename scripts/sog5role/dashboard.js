const chalk = require("chalk");

class Dashboard {
  constructor() {
    this.lastData = null;
  }

  update(data) {
    this.lastData = data;
    this.render();
  }

  render() {
    console.clear();
    const now = new Date().toLocaleString("tr-TR");

    console.log(chalk.cyan.bold("  ══════════════════════════════════════════════════════════"));
    console.log(chalk.cyan.bold("  │     Smart SOG5 SGA124 - Canli Enerji Izleme Sistemi    │"));
    console.log(chalk.cyan.bold("  ══════════════════════════════════════════════════════════"));
    console.log(chalk.gray(`  Son Guncelleme: ${now}`));
    console.log("");

    if (!this.lastData) {
      console.log(chalk.yellow("  Veri bekleniyor..."));
      return;
    }

    const d = this.lastData;

    this.renderVoltage(d);
    this.renderCurrent(d);
    this.renderFrequency(d);
    this.renderActivePower(d);
    this.renderInductivePower(d);
    this.renderCapacitivePower(d);
    this.renderCosPhi(d);
    this.renderTHDI(d);
    this.renderEnergy(d);
    this.renderReactiveEnergy(d);

    console.log("");
    console.log(chalk.gray("  ══════════════════════════════════════════════════════════"));
    console.log(chalk.green("  Sistem Calisiyor  |  Cikis: Ctrl+C"));
  }

  renderVoltage(d) {
    const v = d.voltage;
    if (!v) return;
    console.log(chalk.yellow.bold("  GERILIM (V)"));
    console.log(chalk.yellow("  ──────────────────────────────────────────────────────────"));
    console.log(`  Faz:   L1-N: ${this.pad(v.L1, "V")}    L2-N: ${this.pad(v.L2, "V")}    L3-N: ${this.pad(v.L3, "V")}`);
    console.log(`  Hat:   L1-L2: ${this.pad(v["L1-L2"], "V")}  L2-L3: ${this.pad(v["L2-L3"], "V")}  L1-L3: ${this.pad(v["L1-L3"], "V")}`);
    console.log("");
  }

  renderCurrent(d) {
    const c = d.current;
    if (!c) return;
    console.log(chalk.blue.bold("  AKIM (A)"));
    console.log(chalk.blue("  ──────────────────────────────────────────────────────────"));
    console.log(`  L1: ${this.pad(c.L1, "A")}    L2: ${this.pad(c.L2, "A")}    L3: ${this.pad(c.L3, "A")}`);
    console.log("");
  }

  renderFrequency(d) {
    const f = d.frequency;
    if (!f) return;
    console.log(chalk.white.bold("  FREKANS (Hz)"));
    console.log(chalk.white("  ──────────────────────────────────────────────────────────"));
    console.log(`  L1: ${this.pad(f.L1, "Hz")}    L2: ${this.pad(f.L2, "Hz")}    L3: ${this.pad(f.L3, "Hz")}`);
    console.log("");
  }

  renderActivePower(d) {
    const p = d.active_power;
    if (!p) return;
    console.log(chalk.green.bold("  AKTIF GUC (kW)"));
    console.log(chalk.green("  ──────────────────────────────────────────────────────────"));
    const l1 = p.L1 !== null ? (p.L1 / 1000).toFixed(2) : "---";
    const l2 = p.L2 !== null ? (p.L2 / 1000).toFixed(2) : "---";
    const l3 = p.L3 !== null ? (p.L3 / 1000).toFixed(2) : "---";
    const total = p.Total !== null ? (p.Total / 1000).toFixed(2) : "---";
    console.log(`  L1: ${l1.padStart(8)} kW    L2: ${l2.padStart(8)} kW    L3: ${l3.padStart(8)} kW`);
    console.log(chalk.white.bold(`  TOPLAM: ${total.padStart(8)} kW`));
    console.log("");
  }

  renderInductivePower(d) {
    const p = d.inductive_power;
    if (!p) return;
    console.log(chalk.magenta.bold("  INDUKTIF GUC (kVAr)"));
    console.log(chalk.magenta("  ──────────────────────────────────────────────────────────"));
    const l1 = p.L1 !== null ? (p.L1 / 1000).toFixed(2) : "---";
    const l2 = p.L2 !== null ? (p.L2 / 1000).toFixed(2) : "---";
    const l3 = p.L3 !== null ? (p.L3 / 1000).toFixed(2) : "---";
    const total = p.Total !== null ? (p.Total / 1000).toFixed(2) : "---";
    console.log(`  L1: ${l1.padStart(8)} kVAr   L2: ${l2.padStart(8)} kVAr   L3: ${l3.padStart(8)} kVAr`);
    console.log(`  TOPLAM: ${total.padStart(8)} kVAr`);
    console.log("");
  }

  renderCapacitivePower(d) {
    const p = d.capacitive_power;
    if (!p) return;
    console.log(chalk.cyan.bold("  KAPASITIF GUC (kVAr)"));
    console.log(chalk.cyan("  ──────────────────────────────────────────────────────────"));
    const l1 = p.L1 !== null ? (p.L1 / 1000).toFixed(2) : "---";
    const l2 = p.L2 !== null ? (p.L2 / 1000).toFixed(2) : "---";
    const l3 = p.L3 !== null ? (p.L3 / 1000).toFixed(2) : "---";
    const total = p.Total !== null ? (p.Total / 1000).toFixed(2) : "---";
    console.log(`  L1: ${l1.padStart(8)} kVAr   L2: ${l2.padStart(8)} kVAr   L3: ${l3.padStart(8)} kVAr`);
    console.log(`  TOPLAM: ${total.padStart(8)} kVAr`);
    console.log("");
  }

  renderCosPhi(d) {
    const c = d.cos_phi;
    if (!c) return;
    console.log(chalk.yellow.bold("  COS φ"));
    console.log(chalk.yellow("  ──────────────────────────────────────────────────────────"));
    const l1 = c.L1 !== null ? c.L1.toFixed(2) : "---";
    const l2 = c.L2 !== null ? c.L2.toFixed(2) : "---";
    const l3 = c.L3 !== null ? c.L3.toFixed(2) : "---";
    console.log(`  L1: ${l1.padStart(6)}    L2: ${l2.padStart(6)}    L3: ${l3.padStart(6)}`);
    console.log("");
  }

  renderTHDI(d) {
    const t = d.thdi;
    if (!t) return;
    console.log(chalk.gray.bold("  THDI (%)"));
    console.log(chalk.gray("  ──────────────────────────────────────────────────────────"));
    console.log(`  L1: ${this.pad(t.L1, "%")}    L2: ${this.pad(t.L2, "%")}    L3: ${this.pad(t.L3, "%")}`);
    console.log("");
  }

  renderEnergy(d) {
    const e = d.active_energy_consumption;
    if (!e) return;
    console.log(chalk.green.bold("  AKTIF ENERJI TUKETIMI (kWh)"));
    console.log(chalk.green("  ──────────────────────────────────────────────────────────"));
    const l1 = e.L1 !== null ? (e.L1 / 1000).toFixed(2) : "---";
    const l2 = e.L2 !== null ? (e.L2 / 1000).toFixed(2) : "---";
    const l3 = e.L3 !== null ? (e.L3 / 1000).toFixed(2) : "---";
    const total = e.Total !== null ? (e.Total / 1000).toFixed(2) : "---";
    console.log(`  L1: ${l1.padStart(12)} kWh   L2: ${l2.padStart(12)} kWh   L3: ${l3.padStart(12)} kWh`);
    console.log(chalk.white.bold(`  TOPLAM: ${total.padStart(12)} kWh`));
    console.log("");
  }

  renderReactiveEnergy(d) {
    const ie = d.inductive_energy;
    const ce = d.capacitive_energy;
    if (!ie && !ce) return;
    console.log(chalk.magenta.bold("  REAKTIF ENERJI (kVArh)"));
    console.log(chalk.magenta("  ──────────────────────────────────────────────────────────"));
    if (ie) {
      const it = (ie.L1 || 0) + (ie.L2 || 0) + (ie.L3 || 0);
      console.log(`  Induktif Toplam:   ${((it) / 1000).toFixed(2)} kWh`);
    }
    if (ce) {
      const ct = (ce.L1 || 0) + (ce.L2 || 0) + (ce.L3 || 0);
      console.log(`  Kapasitif Toplam:  ${((ct) / 1000).toFixed(2)} kWh`);
    }
    console.log("");
  }

  pad(val, unit) {
    if (val === null || val === undefined) return `  --- ${unit}`;
    const s = typeof val === "number" ? val.toFixed(1) : val.toString();
    return `${s} ${unit}`;
  }
}

module.exports = Dashboard;
