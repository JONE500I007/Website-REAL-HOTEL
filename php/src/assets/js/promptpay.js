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

// Lets someone paying with only the one phone in hand (no second device to
// scan the screen with) save the QR as a PNG instead, then open it from
// their banking app's "scan from photo/gallery" option.
function downloadPromptPayQR(containerEl, filename) {
    var svgEl = containerEl.querySelector("svg");
    if (!svgEl) return;

    var svgData = new XMLSerializer().serializeToString(svgEl);
    var svgBlob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    var svgUrl = URL.createObjectURL(svgBlob);

    var img = new Image();
    img.onload = function () {
        // Render well above the on-screen SVG's native size so the saved
        // image still scans cleanly when viewed full-screen or zoomed.
        var size = 800;
        var canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;

        var ctx = canvas.getContext("2d");
        ctx.imageSmoothingEnabled = false; // keep QR module edges crisp, not blurred
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(img, 0, 0, size, size);
        URL.revokeObjectURL(svgUrl);

        canvas.toBlob(function (pngBlob) {
            var pngUrl = URL.createObjectURL(pngBlob);
            var link = document.createElement("a");
            link.href = pngUrl;
            link.download = filename || "promptpay-qr.png";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(pngUrl);
        }, "image/png");
    };
    img.src = svgUrl;
}
