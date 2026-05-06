const fs = require("fs");
const pdfParse = require("pdf-parse");

async function extractModbusInfo() {
  const dataBuffer = fs.readFileSync("smart-relay-manual.pdf");
  const data = await pdfParse(dataBuffer);
  const text = data.text;

  const lines = text.split("\n");
  let counter = 0;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].toLowerCase();
    if (
      line.includes("modbus") ||
      line.includes("register") ||
      line.includes("4000") ||
      line.includes("voltage") ||
      line.includes("gerilim") ||
      line.includes("power") ||
      line.includes("aktif") ||
      line.includes("reaktif") ||
      line.includes("kapasitif") ||
      line.includes("energy") ||
      line.includes("tuketim") ||
      line.includes("frequency") ||
      line.includes("frekans") ||
      line.includes("cos fi") ||
      line.includes("harmonik")
    ) {
      const start = Math.max(0, i - 1);
      const end = Math.min(lines.length, i + 6);
      for (let j = start; j < end; j++) {
        console.log(`${j}: ${lines[j]}`);
      }
      console.log("---");
      counter++;
      if (counter > 200) break;
    }
  }

  if (counter === 0) {
    console.log("Modbus bilgisi bulunamadi. Ilk 5000 karakter:");
    console.log(text.substring(0, 5000));
  }
}

extractModbusInfo().catch(console.error);
