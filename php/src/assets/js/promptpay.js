// Builds a Thai QR Payment (PromptPay) EMVCo payload and renders it as an
// SVG QR code. No network calls, no external services — the payload is
// generated entirely client-side per the EMVCo Merchant Presented QR spec,
// the same one PromptPay-compatible banking apps read.

function ppTag(id, value) {
    var len = ("00" + value.length).slice(-2);
    return id + len + value;
}

function ppSanitizeTarget(id) {
    return id.replace(/[^0-9]/g, "");
}

function ppFormatTarget(id) {
    var numbers = ppSanitizeTarget(id);
    if (numbers.length >= 13) return numbers;
    // Mobile numbers: drop the leading 0, prefix with country code 66,
    // left-pad with zeros to the fixed 13-digit PromptPay proxy length.
    var withCountryCode = numbers.replace(/^0/, "66");
    return ("0000000000000" + withCountryCode).slice(-13);
}

// CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF, no reflect, no xorout) —
// the checksum algorithm the EMVCo QR spec requires for the trailing "63" tag.
function ppCrc16(str) {
    var crc = 0xFFFF;
    for (var i = 0; i < str.length; i++) {
        crc ^= (str.charCodeAt(i) << 8);
        for (var j = 0; j < 8; j++) {
            crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1);
            crc &= 0xFFFF;
        }
    }
    return ("0000" + crc.toString(16).toUpperCase()).slice(-4);
}

function buildPromptPayPayload(target, amount) {
    var sanitized = ppSanitizeTarget(target);
    var targetTag = sanitized.length >= 15 ? "03" : sanitized.length >= 13 ? "02" : "01";

    var merchantInfo =
        ppTag("00", "A000000677010111") +
        ppTag(targetTag, ppFormatTarget(target));

    var parts = [
        ppTag("00", "01"),                       // Payload Format Indicator
        ppTag("01", amount ? "12" : "11"),        // Point of Initiation Method
        ppTag("29", merchantInfo),                // Merchant Account Info (PromptPay)
        ppTag("58", "TH"),                        // Country Code
        ppTag("53", "764"),                       // Currency: THB
    ];
    if (amount) {
        parts.push(ppTag("54", Number(amount).toFixed(2))); // Transaction Amount
    }

    var withoutCrc = parts.join("") + "6304";
    return withoutCrc + ppCrc16(withoutCrc);
}

function renderPromptPayQR(containerEl, target, amount) {
    var payload = buildPromptPayPayload(target, amount);
    var qr = qrcode(0, "M"); // typeNumber 0 = auto-detect smallest size
    qr.addData(payload);
    qr.make();
    containerEl.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2 });
    return payload;
}
