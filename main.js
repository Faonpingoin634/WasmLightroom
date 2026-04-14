let wasmModule = null;
let originalImageData = null;
let uploadedFile = null;
let savedPhotoId = null; // ID BDD après le premier save
const canvas = document.getElementById("main-canvas");
const ctx = canvas.getContext("2d");

const currentRecipe = {
  filter: null,      // 'grayscale' | 'sepia' | 'invert' | 'blur' | null
  brightness: 0,
  contrast: 0,
};

// Mode retouche ciblée
let zoneMode = false;
let zoneRadius = 150;

const zoneCursor = document.getElementById("zone-cursor");

function updateZoneCursor(clientX, clientY) {
  const rect = canvas.getBoundingClientRect();
  const scaleX = rect.width / canvas.width;
  const displayRadius = zoneRadius * scaleX;
  const size = displayRadius * 2;
  zoneCursor.style.left   = (clientX - displayRadius) + "px";
  zoneCursor.style.top    = (clientY - displayRadius) + "px";
  zoneCursor.style.width  = size + "px";
  zoneCursor.style.height = size + "px";
}

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

  uploadedFile = file;

  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.onload = () => {
      canvas.width = img.width;
      canvas.height = img.height;
      ctx.drawImage(img, 0, 0);
      originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

      // Reset recipe on new image
      currentRecipe.filter = null;
      currentRecipe.brightness = 0;
      currentRecipe.contrast = 0;
      savedPhotoId = null;
      document.getElementById("slider-brightness").value = 0;
      document.getElementById("val-brightness").textContent = "0";
      document.getElementById("slider-contrast").value = 0;
      document.getElementById("val-contrast").textContent = "0";

      document.getElementById("canvas-container").style.alignItems = "flex-start";
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
        "btn-save",
        "btn-zone-mode",
        "slider-brightness",
        "slider-contrast",
      ].forEach((id) => (document.getElementById(id).disabled = false));
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

/**
 * Applique le contenu de currentRecipe sur originalImageData
 * en enchaînant : filtre → luminosité → contraste
 */
function applyRecipe() {
  if (!wasmModule || !originalImageData) return;

  // Étape 1 : copie de l'image originale
  let imageData = new ImageData(
    new Uint8ClampedArray(originalImageData.data),
    originalImageData.width,
    originalImageData.height
  );

  // Étape 2 : filtre discret (grayscale / sepia / invert / blur)
  if (currentRecipe.filter) {
    imageData = applyWasmFn(imageData, currentRecipe.filter, null);
  }

  // Étape 3 : luminosité
  if (currentRecipe.brightness !== 0) {
    imageData = applyWasmFn(imageData, "brightness", currentRecipe.brightness);
  }

  // Étape 4 : contraste
  if (currentRecipe.contrast !== 0) {
    imageData = applyWasmFn(imageData, "contrast", currentRecipe.contrast);
  }

  ctx.putImageData(imageData, 0, 0);
}

/**
 * Exécute une fonction Wasm sur un ImageData et retourne le résultat
 */
function applyWasmFn(imageData, filterName, value) {
  const { width, height, data } = imageData;
  const numBytes = data.length;
  const ptr = wasmModule._malloc(numBytes);
  wasmModule.HEAPU8.set(data, ptr);

  const fnName = `_apply_${filterName}`;
  if (typeof wasmModule[fnName] === "function") {
    if (value !== null) {
      wasmModule[fnName](ptr, width, height, value);
    } else {
      wasmModule[fnName](ptr, width, height);
    }
  } else {
    console.warn(`Fonction Wasm "${fnName}" introuvable.`);
  }

  const result = new ImageData(
    new Uint8ClampedArray(wasmModule.HEAPU8.subarray(ptr, ptr + numBytes)),
    width,
    height
  );
  wasmModule._free(ptr);
  return result;
}

// --- Boutons filtres ---

document.getElementById("btn-original").addEventListener("click", () => {
  currentRecipe.filter = null;
  currentRecipe.brightness = 0;
  currentRecipe.contrast = 0;
  document.getElementById("slider-brightness").value = 0;
  document.getElementById("val-brightness").textContent = "0";
  document.getElementById("slider-contrast").value = 0;
  document.getElementById("val-contrast").textContent = "0";
  if (originalImageData) ctx.putImageData(originalImageData, 0, 0);
});

["grayscale", "sepia", "invert", "blur"].forEach((name) => {
  document.getElementById(`btn-${name}`).addEventListener("click", () => {
    currentRecipe.filter = name;
    applyRecipe();
  });
});

// --- Sliders ---

document.getElementById("slider-brightness").addEventListener("input", (e) => {
  currentRecipe.brightness = parseInt(e.target.value);
  document.getElementById("val-brightness").textContent = e.target.value;
  if (!zoneMode) applyRecipe();
});

document.getElementById("slider-contrast").addEventListener("input", (e) => {
  currentRecipe.contrast = parseInt(e.target.value);
  document.getElementById("val-contrast").textContent = e.target.value;
  if (!zoneMode) applyRecipe();
});

// --- Export PNG ---

document.getElementById("btn-export").addEventListener("click", () => {
  const link = document.createElement("a");
  link.download = "image_editee.png";
  link.href = canvas.toDataURL("image/png");
  link.click();
});

// --- Sauvegarde AJAX ---

document.getElementById("btn-save").addEventListener("click", async () => {
  if (!originalImageData) return;

  const btn = document.getElementById("btn-save");
  btn.disabled = true;
  btn.textContent = "Sauvegarde...";

  try {
    const formData = new FormData();
    formData.append("recipe", JSON.stringify(currentRecipe));

    if (savedPhotoId) {
      // Photo déjà enregistrée → on met juste à jour la recette
      formData.append("photo_id", savedPhotoId);
    } else {
      // Premier save → on envoie l'image originale intacte
      if (!uploadedFile) {
        btn.textContent = "Aucune image";
        return;
      }
      formData.append("original", uploadedFile, uploadedFile.name);
    }

    const response = await fetch("save.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      savedPhotoId = result.id; // retient l'ID pour les saves suivants
      btn.textContent = "Sauvegardé !";
      btn.classList.replace("btn-primary", "btn-success");
    } else {
      btn.textContent = "Erreur";
      btn.classList.replace("btn-primary", "btn-danger");
      console.error(result.error);
    }
  } catch (err) {
    btn.textContent = "Erreur réseau";
    console.error(err);
  } finally {
    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = "Sauvegarder";
      btn.className = "btn btn-primary btn-filter";
    }, 2500);
  }
});

// --- Retouche ciblée par zone ---

document.getElementById("btn-zone-mode").addEventListener("click", () => {
  zoneMode = !zoneMode;
  const btn = document.getElementById("btn-zone-mode");
  btn.textContent = zoneMode ? "Mode zone : ON" : "Mode zone : OFF";
  btn.classList.toggle("btn-warning", zoneMode);
  btn.classList.toggle("btn-outline-warning", !zoneMode);
  canvas.style.cursor = zoneMode ? "none" : "default";
  document.getElementById("zone-controls").style.display = zoneMode ? "block" : "none";
  if (!zoneMode) zoneCursor.style.display = "none";
});

document.getElementById("slider-zone-radius").addEventListener("input", (e) => {
  zoneRadius = parseInt(e.target.value);
  document.getElementById("val-zone-radius").textContent = zoneRadius;
});

canvas.addEventListener("mousemove", (e) => {
  if (!zoneMode) return;
  zoneCursor.style.display = "block";
  updateZoneCursor(e.clientX, e.clientY);
});

canvas.addEventListener("mouseleave", () => {
  if (zoneMode) zoneCursor.style.display = "none";
});

canvas.addEventListener("click", (e) => {
  if (!zoneMode) { console.log("[zone] zoneMode=false, skip"); return; }
  if (!wasmModule) { console.log("[zone] wasmModule null, skip"); return; }
  if (!originalImageData) { console.log("[zone] originalImageData null, skip"); return; }

  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width  / rect.width;
  const scaleY = canvas.height / rect.height;
  const cx = Math.round((e.clientX - rect.left) * scaleX);
  const cy = Math.round((e.clientY - rect.top)  * scaleY);

  console.log(`[zone] click cx=${cx} cy=${cy} radius=${zoneRadius} filter=${currentRecipe.filter || "grayscale"}`);
  applyZoneFilter(cx, cy, zoneRadius);
});

function applyZoneFilter(cx, cy, radius) {
  const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const { width, height, data } = imageData;
  const numBytes = data.length;

  const ptr = wasmModule._malloc(numBytes);
  wasmModule.HEAPU8.set(data, ptr);

  try {
    // Étape 1 : filtre discret dans la zone
    if (currentRecipe.filter) {
      const fn = wasmModule[`_apply_${currentRecipe.filter}_zone`];
      if (typeof fn === "function") {
        fn(ptr, width, height, cx, cy, radius);
      }
    }

    // Étape 2 : luminosité dans la zone
    if (currentRecipe.brightness !== 0) {
      wasmModule._apply_brightness_zone(ptr, width, height, currentRecipe.brightness, cx, cy, radius);
    }

    // Étape 3 : contraste dans la zone
    if (currentRecipe.contrast !== 0) {
      wasmModule._apply_contrast_zone(ptr, width, height, currentRecipe.contrast, cx, cy, radius);
    }

    data.set(wasmModule.HEAPU8.subarray(ptr, ptr + numBytes));
    ctx.putImageData(imageData, 0, 0);
  } catch (err) {
    console.error("[zone] erreur WASM:", err);
  } finally {
    wasmModule._free(ptr);
  }
}

// --- Drag & drop ---

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
