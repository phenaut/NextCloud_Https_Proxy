<?php
/**
 * Fichier : extra-apps/https_proxy/templates/main.php
 */
?>

<style>
        #content-app-https_proxy,
    #app-content {
        padding: 0 !important;
        width: 100%;
        height: 100%;
    }

    #proxy-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 50px);
        width: 100%;
        background: #f0f0f0;
        font-family: sans-serif;
    }

    #proxy-header {
        padding: 6px 12px;
        background: #1e1e2e;
        border-bottom: 2px solid #3a3a5c;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-shrink: 0;
        z-index: 100;
    }

    .proxy-nav-btn {
        background: #2a2a4a;
        color: #ccc;
        border: 1px solid #444;
        border-radius: 4px;
        padding: 6px 10px;
        cursor: pointer;
        font-size: 15px;
        line-height: 1;
        transition: background 0.2s;
    }
    .proxy-nav-btn:hover:not(:disabled) {
        background: #3a3a6a;
        color: #fff;
    }
    .proxy-nav-btn:disabled {
        opacity: 0.35;
        cursor: default;
    }

    #proxy-url {
        flex-grow: 1;
        padding: 7px 12px;
        border: 1px solid #444;
        border-radius: 4px;
        font-size: 14px;
        background: #12121f;
        color: #e0e0f0;
        outline: none;
    }
    #proxy-url:focus {
        border-color: #6699cc;
        box-shadow: 0 0 0 2px rgba(102,153,204,0.25);
    }

    #proxy-btn {
        padding: 7px 16px;
        background: #3366cc;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        transition: background 0.2s;
    }
    #proxy-btn:hover {
        background: #4477dd;
    }

    #proxy-frame-container {
        flex-grow: 1;
        width: 100%;
        position: relative;
        overflow: hidden;
        background: #fff;
    }

    #proxy-frame {
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }
</style>

<div id="proxy-wrapper">
    <div id="proxy-header">
        <button id="proxy-back"   class="proxy-nav-btn" title="Retour"    disabled>&#8592;</button>
        <button id="proxy-fwd"    class="proxy-nav-btn" title="Avant"     disabled>&#8594;</button>
        <button id="proxy-reload" class="proxy-nav-btn" title="Recharger" disabled>&#8635;</button>

        <input type="text"
               id="proxy-url"
               placeholder="https://www.example.com"
               value="https://www.wikipedia.org"
               autocomplete="off"
               spellcheck="false">

        <button id="proxy-btn">&#9658; Aller</button>
    </div>

    <div id="proxy-frame-container">
        <iframe
            id="proxy-frame"
            sandbox="allow-scripts allow-forms allow-same-origin allow-popups"
            referrerpolicy="no-referrer"
            src="about:blank">
        </iframe>
    </div>
</div>
