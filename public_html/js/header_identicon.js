/**
 * ファイル: public_html\js\header_identicon.js
 * 使い道: ヘッダーのIdenticon描画を行います。
 * 定義されている関数: なし
 */


(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const userId = document.body.dataset.currentUserId; // userId はデフォルトシード生成のため維持
        const activeIdenticonString = document.body.dataset.activeIdenticon; // "color,p1,p2"
        const canvas = document.getElementById('header-identicon');

        if (canvas) {
            let seeds = null;
            if (activeIdenticonString) {
                seeds = window.parseSeedsString(activeIdenticonString); // Assuming parseSeedsString is global or accessible
            }
            
            if (!seeds) {
                const defaultSeed = parseInt(userId || 0);
                seeds = { color: defaultSeed, p1: defaultSeed, p2: defaultSeed };
            }
            window.IdenticonRenderer.createIdenticon(seeds, canvas);
        }
    });

})();



