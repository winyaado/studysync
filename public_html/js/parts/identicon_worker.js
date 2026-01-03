/**
 * ファイル: public_html\js\parts\identicon_worker.js
 * 使い道: Identicon探索のWebWorker処理を行います。
 * 定義されている関数:
 *   - simpleHash
 */



function simpleHash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash |= 0; // Convert to 32bit integer
    }
    return Math.abs(hash);
}

self.onmessage = function(e) {
    const { userId, targetPattern, targetHues } = e.data;
    console.log('[Worker] Received job:', { userId, targetPattern, targetHues });

    const searchWithColor = Array.isArray(targetHues) && targetHues.length > 0;
    const hueTolerance = 10; // 色相の許容範囲 (±10)
    let seed = 0;
    const reportInterval = 1_000_000; // Report progress every 1M attempts

    while (true) {
        const hash = simpleHash(String(userId) + String(seed));
        const pattern = hash & 0x1FFFFFF; // Get 25 bits for the pattern

        if (pattern === targetPattern) {
            self.postMessage({ type: 'shape_match', seed: seed });

            if (searchWithColor) {
                const baseHue = hash % 360;
                const hueMatch = targetHues.some(targetHue => 
                    Math.abs(targetHue - baseHue) <= hueTolerance ||
                    Math.abs(targetHue - baseHue) >= (360 - hueTolerance) // handles wrapping around 0/360
                );

                if (hueMatch) {
                    console.log('[Worker] Found full match (shape and color)!', seed);
                    self.postMessage({ type: 'found', seed: seed, attempts: seed });
                    self.close(); // Terminate the worker
                    return;
                }
            } else {
                console.log('[Worker] Found shape match!', seed);
                self.postMessage({ type: 'found', seed: seed, attempts: seed });
                self.close(); // Terminate the worker
                return;
            }
        }

        if (seed > 0 && seed % reportInterval === 0) {
            self.postMessage({ type: 'progress', attempts: seed });
        }
        
        seed++;
    }
};



