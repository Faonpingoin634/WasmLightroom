export class ImageEditor {
  #wasm;
  #canvas;
  #ctx;
  #originalImageData = null;

  constructor(canvas, wasmModule) {
    this.#canvas = canvas;
    this.#ctx = canvas.getContext('2d');
    this.#wasm = wasmModule;
  }

  get originalImageData() {
    return this.#originalImageData;
  }

  loadImage(file) {
    return new Promise((resolve, reject) => {
      if (!file || !file.type.startsWith('image/')) {
        reject(new Error('Fichier invalide.'));
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          this.#canvas.width = img.width;
          this.#canvas.height = img.height;
          this.#ctx.drawImage(img, 0, 0);
          this.#originalImageData = this.#ctx.getImageData(0, 0, img.width, img.height);
          resolve({ width: img.width, height: img.height, size: file.size });
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  applyRecipe(recipe) {
    if (!this.#wasm || !this.#originalImageData) return;

    let imageData = new ImageData(
      new Uint8ClampedArray(this.#originalImageData.data),
      this.#originalImageData.width,
      this.#originalImageData.height
    );

    if (recipe.filter) imageData = this.#applyWasmFn(imageData, recipe.filter, null);
    if (recipe.brightness !== 0) imageData = this.#applyWasmFn(imageData, 'brightness', recipe.brightness);
    if (recipe.contrast !== 0) imageData = this.#applyWasmFn(imageData, 'contrast', recipe.contrast);

    this.#ctx.putImageData(imageData, 0, 0);
  }

  applyZoneFilter(cx, cy, radius, recipe) {
    const imageData = this.#ctx.getImageData(0, 0, this.#canvas.width, this.#canvas.height);
    const { width, height, data } = imageData;
    const numBytes = data.length;
    const ptr = this.#wasm._malloc(numBytes);
    this.#wasm.HEAPU8.set(data, ptr);

    try {
      if (recipe.filter) {
        const fn = this.#wasm[`_apply_${recipe.filter}_zone`];
        if (typeof fn === 'function') fn(ptr, width, height, cx, cy, radius);
      }
      if (recipe.brightness !== 0) {
        this.#wasm._apply_brightness_zone(ptr, width, height, recipe.brightness, cx, cy, radius);
      }
      if (recipe.contrast !== 0) {
        this.#wasm._apply_contrast_zone(ptr, width, height, recipe.contrast, cx, cy, radius);
      }
      data.set(this.#wasm.HEAPU8.subarray(ptr, ptr + numBytes));
      this.#ctx.putImageData(imageData, 0, 0);
    } finally {
      this.#wasm._free(ptr);
    }
  }

  restoreOriginal() {
    if (this.#originalImageData) this.#ctx.putImageData(this.#originalImageData, 0, 0);
  }

  snapshot(recipe) {
    const snap = this.#ctx.getImageData(0, 0, this.#canvas.width, this.#canvas.height);
    return {
      data: new Uint8ClampedArray(snap.data),
      width: snap.width,
      height: snap.height,
      recipe: { ...recipe },
    };
  }

  restore(snap) {
    this.#ctx.putImageData(new ImageData(snap.data, snap.width, snap.height), 0, 0);
  }

  exportPng() {
    return this.#canvas.toDataURL('image/png');
  }

  #applyWasmFn(imageData, filterName, value) {
    const { width, height, data } = imageData;
    const numBytes = data.length;
    const ptr = this.#wasm._malloc(numBytes);
    this.#wasm.HEAPU8.set(data, ptr);

    const fn = this.#wasm[`_apply_${filterName}`];
    if (typeof fn === 'function') {
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
