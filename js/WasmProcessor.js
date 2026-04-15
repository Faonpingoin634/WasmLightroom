export class WasmProcessor {
  #wasm;
  #canvas;
  #ctx;

  constructor(canvas, wasmModule) {
    this.#canvas = canvas;
    this.#ctx = canvas.getContext("2d");
    this.#wasm = wasmModule;
  }

  applyRecipe(originalImageData, recipe) {
    let imageData = new ImageData(
      new Uint8ClampedArray(originalImageData.data),
      originalImageData.width,
      originalImageData.height
    );

    if (recipe.filter)          imageData = this.#call(imageData, recipe.filter, null);
    if (recipe.brightness !== 0) imageData = this.#call(imageData, "brightness", recipe.brightness);
    if (recipe.contrast   !== 0) imageData = this.#call(imageData, "contrast",   recipe.contrast);

    this.#ctx.putImageData(imageData, 0, 0);
  }

  applyZoneFilter(recipe, cx, cy, radius) {
    const imageData = this.#ctx.getImageData(0, 0, this.#canvas.width, this.#canvas.height);
    const { width, height, data } = imageData;
    const numBytes = data.length;
    const ptr = this.#wasm._malloc(numBytes);
    this.#wasm.HEAPU8.set(data, ptr);

    try {
      if (recipe.filter) {
        const fn = this.#wasm[`_apply_${recipe.filter}_zone`];
        if (typeof fn === "function") fn(ptr, width, height, cx, cy, radius);
      }
      if (recipe.brightness !== 0)
        this.#wasm._apply_brightness_zone(ptr, width, height, recipe.brightness, cx, cy, radius);
      if (recipe.contrast !== 0)
        this.#wasm._apply_contrast_zone(ptr, width, height, recipe.contrast, cx, cy, radius);

      data.set(this.#wasm.HEAPU8.subarray(ptr, ptr + numBytes));
      this.#ctx.putImageData(imageData, 0, 0);
    } finally {
      this.#wasm._free(ptr);
    }
  }

  #call(imageData, filterName, value) {
    const { width, height, data } = imageData;
    const numBytes = data.length;
    const ptr = this.#wasm._malloc(numBytes);
    this.#wasm.HEAPU8.set(data, ptr);

    const fn = this.#wasm[`_apply_${filterName}`];
    if (typeof fn === "function") {
      value !== null ? fn(ptr, width, height, value) : fn(ptr, width, height);
    }

    const result = new ImageData(
      new Uint8ClampedArray(this.#wasm.HEAPU8.subarray(ptr, ptr + numBytes)),
      width,
      height
    );
    this.#wasm._free(ptr);
    return result;
  }
}
