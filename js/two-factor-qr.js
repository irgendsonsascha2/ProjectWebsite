(function () {
    var canvas = document.getElementById('two-factor-qr-canvas');
    if (!canvas || typeof QRCode === 'undefined') {
        return;
    }

    var uri = canvas.getAttribute('data-provisioning-uri');
    if (!uri) {
        return;
    }

    QRCode.toCanvas(canvas, uri, {
        width: 200,
        margin: 2,
        errorCorrectionLevel: 'M',
    }, function (err) {
        if (err) {
            console.error('2FA QR:', err);
            var fallback = document.getElementById('two-factor-qr-fallback');
            if (fallback) {
                fallback.hidden = false;
            }
        }
    });
})();
