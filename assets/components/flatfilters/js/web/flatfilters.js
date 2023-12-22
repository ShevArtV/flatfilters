'use strict';

class FlatFilters{
    constructor() {
        if(window.FlatFilters) return window.FlatFilters;
        this.pathToConfigs = SendIt.getComponentCookie('ffJsConfigPath', 'FlatFilters');
        this.events = {
            init: 'ff:init',
        }

        this.config = {};
        this.loadConfigs().then(() => {
            window.FlatFilters = this;
            document.dispatchEvent(new CustomEvent(this.events.init, {}));
        });
    }

    async loadConfigs() {
        await this.importModule(this.pathToConfigs, 'config');
        await this.initialize();
    }

    async initialize() {
        for (let k in this.config) {
            await this.importModule(this.config[k]['pathToScripts'], k);
        }
    }

    async importModule(pathToModule, property) {
        const {default: moduleName} = await import(pathToModule);
        if (property === "config") {
            this[property] = moduleName();
        } else {
            this[property] = new moduleName(this.config[property])
        }
        try {

        } catch (e) {
            throw new Error(e);
        }
    }
}

document.addEventListener('si:init', () => new FlatFilters());