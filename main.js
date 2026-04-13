let wasmModule = null;
let originalImageData = null;
const canvas = document.getElementById("main-canvas");
const ctx = canvas.getContext("2d");

async function loadWasm() {
  const statusBadge = document.getElementById("wasm-status");
  try {
    await new Promise((resolve, reject) => {
      if (typeof Module === "undefined") {
        reject(new Error("Module Emscripten introuvable."));
        return;
      }
      if (Module.calledRun) {
        resolve();
      } else {
        Module.onRuntimeInitialized = resolve;
      }
    });

    wasmModule = Module;
    statusBadge.textContent = "WebAssembly : prêt";
    statusBadge.className = "badge bg-success";
  } catch (err) {
    statusBadge.textContent = "WebAssembly : erreur";
    statusBadge.className = "badge bg-danger";
    console.warn(err.message);
  }
}

function loadImage(file) {
  if (!file || !file.type.startsWith("image/")) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.onload = () => {
      canvas.width = img.width;
      canvas.height = img.height;
      ctx.drawImage(img, 0, 0);
      originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

      document.getElementById("canvas-container").style.alignItems =
        "flex-start";
      document.getElementById("drop-hint").style.display = "none";
      canvas.style.display = "block";

      document.getElementById("img-info").textContent =
        `${img.width} × ${img.height} px — ${(file.size / 1024).toFixed(1)} Ko`;

      [
        "btn-original",
        "btn-grayscale",
        "btn-sepia",
        "btn-invert",
        "btn-blur",
        "btn-export",
        "slider-brightness",
        "slider-contrast",
      ].forEach((id) => (document.getElementById(id).disabled = false));
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function applyWasmFilter(filterName) {
  if (!wasmModule) {
    alert("WebAssembly n'est pas encore chargé.");
    return;
  }
  if (!originalImageData) {
    alert("Chargez d'abord une image.");
    return;
  }

  const imageData = new ImageData(
    new Uint8ClampedArray(originalImageData.data),
    originalImageData.width,
    originalImageData.height,
  );

  const { width, height, data } = imageData;
  const numBytes = data.length;

  const ptr = wasmModule._malloc(numBytes);
  wasmModule.HEAPU8.set(data, ptr);

  const fnName = `_apply_${filterName}`;
  if (typeof wasmModule[fnName] === "function") {
    wasmModule[fnName](ptr, width, height);
  } else {
    console.warn(`Fonction Wasm "${fnName}" introuvable.`);
  }

  data.set(wasmModule.HEAPU8.subarray(ptr, ptr + numBytes));
  wasmModule._free(ptr);

  ctx.putImageData(imageData, 0, 0);
}

function applyWasmFilterWithValue(filterName, value) {
  if (!wasmModule || !originalImageData) return;

  const imageData = new ImageData(
    new Uint8ClampedArray(originalImageData.data),
    originalImageData.width,
    originalImageData.height,
  );

  const { width, height, data } = imageData;
  const numBytes = data.length;

  const ptr = wasmModule._malloc(numBytes);
  wasmModule.HEAPU8.set(data, ptr);

  const fnName = `_apply_${filterName}`;
  if (typeof wasmModule[fnName] === "function") {
    wasmModule[fnName](ptr, width, height, value);
  } else {
    console.warn(`Fonction Wasm "${fnName}" introuvable.`);
  }

  data.set(wasmModule.HEAPU8.subarray(ptr, ptr + numBytes));
  wasmModule._free(ptr);

  ctx.putImageData(imageData, 0, 0);
}

document.getElementById("btn-original").addEventListener("click", () => {
  if (originalImageData) ctx.putImageData(originalImageData, 0, 0);
});

document
  .getElementById("btn-grayscale")
  .addEventListener("click", () => applyWasmFilter("grayscale"));
document
  .getElementById("btn-sepia")
  .addEventListener("click", () => applyWasmFilter("sepia"));
document
  .getElementById("btn-invert")
  .addEventListener("click", () => applyWasmFilter("invert"));
document
  .getElementById("btn-blur")
  .addEventListener("click", () => applyWasmFilter("blur"));

document.getElementById("slider-brightness").addEventListener("input", (e) => {
  document.getElementById("val-brightness").textContent = e.target.value;
  applyWasmFilterWithValue("brightness", parseInt(e.target.value));
});

document.getElementById("slider-contrast").addEventListener("input", (e) => {
  document.getElementById("val-contrast").textContent = e.target.value;
  applyWasmFilterWithValue("contrast", parseInt(e.target.value));
});

document.getElementById("btn-export").addEventListener("click", () => {
  const link = document.createElement("a");
  link.download = "image_editee.png";
  link.href = canvas.toDataURL("image/png");
  link.click();
});

document.getElementById("file-input").addEventListener("change", (e) => {
  loadImage(e.target.files[0]);
});

const container = document.getElementById("canvas-container");
container.addEventListener("dragover", (e) => {
  e.preventDefault();
  container.style.borderColor = "#0d6efd";
});
container.addEventListener("dragleave", () => {
  container.style.borderColor = "#0f3460";
});
container.addEventListener("drop", (e) => {
  e.preventDefault();
  container.style.borderColor = "#0f3460";
  loadImage(e.dataTransfer.files[0]);
});

loadWasm();
