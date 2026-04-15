export class WasmLoader {
  #module = null;

  async load() {
    await new Promise((resolve, reject) => {
      if (typeof Module === "undefined") {
        reject(new Error("Module Emscripten introuvable."));
        return;
      }
      Module.calledRun ? resolve() : (Module.onRuntimeInitialized = resolve);
    });
    this.#module = Module;
    return this.#module;
  }

  get module() {
    return this.#module;
  }
}
