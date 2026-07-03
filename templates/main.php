<?php
/**
 * Fichier : extra-apps/https_proxy/templates/main.php
 */
?>

<style>
    /* Force l'application à prendre toute la place disponible dans Nextcloud */
    #content-app-https_proxy {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
    }

    #proxy-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 50px); /* Ajuste selon la barre de navigation Nextcloud */
        width: 100%;
        background-color: #f8f8f8;
    }

    #proxy-header {
        padding: 10px 20px;
        background: #fff;
        border-bottom: 1px solid #ddd;
        display: flex;
        gap: 10px;
        align-items: center;
        z-index: 100;
    }

    #proxy-url {
        flex-grow: 1;
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 3px;
        font-size: 14px;
    }

    #proxy-frame-container {
        flex-grow: 1;
        width: 100%;
        position: relative;
        background: #fff;
    }

    #proxy-frame {
        width: 100%;
        height: 100%;
        border: none;
    }
</style>

<div id="proxy-wrapper">
    <div id="proxy-header">
        <label for="proxy-url" style="font-weight: bold;">URL :</label>
        <input type="text" id="proxy-url" placeholder="https://www.wikipedia.org" value="https://www.wikipedia.org">
        <button id="proxy-btn" class="primary">Naviguer</button>
    </div>
    <div id="proxy-frame-container">
        <iframe
            id="proxy-frame"
            sandbox="allow-scripts allow-forms allow-same-origin"
            src="about:blank">
        </iframe>
    </div>
</div>
