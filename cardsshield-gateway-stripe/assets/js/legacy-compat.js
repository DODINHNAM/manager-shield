/* Compatibility at the shield iframe boundary; never send both payment commands. */
(function (root) {
    const peers = new WeakMap();
    function originFor(frame) {
        try { return new URL(frame.src, root.location.href).origin; } catch (_) { return null; }
    }
    function normalize(event, frames) {
        const frame = frames.find(frame => frame && frame.contentWindow === event.source && originFor(frame) === event.origin);
        if (!frame || !event.data) return null;
        const data = event.data;
        const name = typeof data === 'string' ? data : data.name;
        if (typeof name !== 'string' || !/^(mecom|lazy)-/.test(name)) return null;
        peers.set(frame.contentWindow, name.startsWith('mecom-') ? 'mecom' : 'lazy');
        const newName = name.replace(/^mecom-/, 'lazy-');
        return { data: typeof data === 'string' ? newName : Object.assign({}, data, { name: newName }) };
    }
    function send(frame, message) {
        if (!frame || !frame.contentWindow) return;
        const origin = originFor(frame);
        if (!origin || origin === 'null') return;
        const protocol = peers.get(frame.contentWindow) || (root.lazyStripeProtocol || {}).protocol || 'lazy';
        const payload = Object.assign({}, message);
        if (protocol === 'mecom') payload.name = payload.name.replace(/^lazy-/, 'mecom-');
        frame.contentWindow.postMessage(payload, origin);
    }
    root.lazyStripeCompat = { normalize, send };
})(window);
