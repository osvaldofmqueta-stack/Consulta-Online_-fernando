import { ReplitConnectors } from "@replit/connectors-sdk";

const input = await new Promise((resolve, reject) => {
  let value = "";
  process.stdin.setEncoding("utf8");
  process.stdin.on("data", (chunk) => { value += chunk; });
  process.stdin.on("end", () => resolve(value));
  process.stdin.on("error", reject);
});

const payload = JSON.parse(input);
const connectors = new ReplitConnectors();
const response = await connectors.proxy("resend", "/emails", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify(payload),
});

if (!response.ok) {
  throw new Error(`Resend returned ${response.status}: ${await response.text()}`);
}

console.log(JSON.stringify({ ok: true }));