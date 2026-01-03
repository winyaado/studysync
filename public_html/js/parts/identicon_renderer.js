/**
 * ファイル: public_html\js\parts\identicon_renderer.js
 * 使い道: Identiconの描画ロジックを提供します。
 * 定義されている関数:
 *   - parseSeedsString
 *   - int_hash_64bit
 *   - hslToRgb
 *   - createIdenticon
 *   - hue2rgb
 */


window.IdenticonRenderer = (function () {
    'use strict';

    /**
     * parseSeedsString の入力を解析します。
     * @param {any} seedsString 入力値
     * @returns {void}
     */
    function parseSeedsString(seedsString) {
        if (!seedsString) return null;
        const parts = seedsString.split(',').map(Number);
        if (parts.length === 3) {
            return { color: parts[0], p1: parts[1], p2: parts[2] };
        }
        console.warn("Invalid seeds string format:", seedsString);
        return null;
    }

    /**
     * int_hash_64bit の処理を行います。
     * @param {any} seed 入力値
     * @returns {void}
     */
    function int_hash_64bit(seed) {
        let state = BigInt(seed);

        const m1 = 0xCBF29CE484222325n;
        const m2 = 0x1BD11ED5n;
        const mask = 0xFFFFFFFFFFFFFFFFn; // 64-bit mask

        state = (state * m1) & mask;
        state ^= (state >> 33n);
        state = (state * m2) & mask;
        state ^= (state >> 23n);
        
        return state;
    }
    
    /**
     * hslToRgb の処理を行います。
     * @param {any} h 入力値
     * @param {any} s 入力値
     * @param {any} l 入力値
     * @returns {void}
     */
    function hslToRgb(h, s, l) {
        let r, g, b;
        if (s === 0) {
            r = g = b = l; // achromatic
        } else {
            /**
             * hue2rgb の処理を行います。
             * @param {any} p 入力値
             * @param {any} q 入力値
             * @param {any} t 入力値
             * @returns {void}
             */
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            };
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }
        return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
    }

    /**
     * createIdenticon の新しいデータを生成します。
     * @param {any} seeds 入力値
     * @param {any} canvas 入力値
     * @returns {void}
     */
    function createIdenticon(seeds, canvas) {
        if (!seeds || !canvas || seeds.color === null || seeds.p1 === null || seeds.p2 === null) {
            const ctx = canvas.getContext('2d');
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            return;
        }

        const color_hash = int_hash_64bit(seeds.color);
        const base_hue = Number(color_hash % 360n);
        const generated_colors = {
            c1: hslToRgb(base_hue / 360, 0.7, 0.6), // Color 1
            c2: hslToRgb(((base_hue + 30) % 360) / 360, 0.7, 0.6) // Color 2 (analogous)
        };
        
        const p1_hash = int_hash_64bit(seeds.p1);
        const p1_mask = p1_hash & ((1n << 25n) - 1n); // Lower 25 bits for pattern 1

        const p2_hash = int_hash_64bit(seeds.p2);
        const p2_mask = p2_hash & ((1n << 25n) - 1n); // Lower 25 bits for pattern 2

        const ctx = canvas.getContext('2d');
        const size = canvas.width;
        const pixel_size = size / 5;

        ctx.clearRect(0, 0, size, size); // Transparent background

        for (let y = 0; y < 5; y++) {
            for (let x = 0; x < 5; x++) {
                const bit_index = BigInt(y * 5 + x);
                
                const is_p1_on = ((p1_mask >> bit_index) & 1n) === 1n;
                const is_p2_on = ((p2_mask >> bit_index) & 1n) === 1n;

                let color_to_draw = null;

                if (is_p2_on) {
                    color_to_draw = generated_colors.c2;
                } else if (is_p1_on) {
                    color_to_draw = generated_colors.c1;
                }

                if (color_to_draw) {
                    ctx.fillStyle = `rgb(${color_to_draw[0]}, ${color_to_draw[1]}, ${color_to_draw[2]})`;
                    ctx.fillRect(x * pixel_size, y * pixel_size, pixel_size, pixel_size);
                }
            }
        }
    }

    window.parseSeedsString = parseSeedsString; // グローバルに公開
    return {
        createIdenticon: createIdenticon
    };
})();



