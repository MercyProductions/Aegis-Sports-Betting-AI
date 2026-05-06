const crypto = require("crypto");

let input = "";
process.stdin.setEncoding("utf8");
process.stdin.on("data", (chunk) => {
    input += chunk;
});

process.stdin.on("end", () => {
    try {
        const payload = JSON.parse(input || "{}");
        const privateKeyPem = String(payload.privateKeyPem || "");
        const message = String(payload.message || "");

        if (!privateKeyPem || !message) {
            throw new Error("Private key and signing message are required.");
        }

        const signer = crypto.createSign("RSA-SHA256");
        signer.update(message);
        signer.end();

        const signature = signer.sign({
            key: privateKeyPem,
            padding: crypto.constants.RSA_PKCS1_PSS_PADDING,
            saltLength: crypto.constants.RSA_PSS_SALTLEN_DIGEST,
        });

        process.stdout.write(JSON.stringify({
            ok: true,
            signature: signature.toString("base64"),
        }));
    } catch (error) {
        process.stdout.write(JSON.stringify({
            ok: false,
            message: error instanceof Error ? error.message : "Kalshi signing failed.",
        }));
        process.exitCode = 1;
    }
});
