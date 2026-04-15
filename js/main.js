import { WasmLoader    } from "./WasmLoader.js";
import { History       } from "./History.js";
import { WasmProcessor } from "./WasmProcessor.js";
import { ZoneMode      } from "./ZoneMode.js";

const canvas = document.getElementById("main-canvas");
const ctx    = canvas.getContext("2d");

const recipe = { filter: null, brightness: 0, contrast: 0 };

let originalImageData = null;
let uploadedFile      = null;
let savedPhotoId      = null;
let processor         = null;

const history  = new History(20);
const zone     = new ZoneMode(canvas, document.getElementById("zone-cursor"));
const loader   = new WasmLoader();

const el = {
  wasmStatus:      document.getElementById("wasm-status"),
  fileInput:       document.getElementById("file-input"),
  canvasContainer: document.getElementById("canvas-container"),
  dropHint:        document.getElementById("drop-hint"),
  imgInfo:         document.getElementById("img-info"),
  btnUndo:         document.getElementById("btn-undo"),
  btnRedo:         document.getElementById("btn-redo"),
  btnSave:         document.getElementById("btn-save"),
  btnZoneMode:     document.getElementById("btn-zone-mode"),
  zoneControls:    document.getElementById("zone-controls"),
  sliderBrightness: document.getElementById("slider-brightness"),
  sliderContrast:   document.getElementById("slider-contrast"),
  valBrightness:    document.getElementById("val-brightness"),
  valContrast:      document.getElementById("val-contrast"),
  sliderZoneRadius: document.getElementById("slider-zone-radius"),
  valZoneRadius:    document.getElementById("val-zone-radius"),
};

loader.load().then((wasm) => {
  processor = new WasmProcessor(canvas, wasm);
  el.wasmStatus.textContent = "WebAssembly : prêt";
  el.wasmStatus.className   = "badge bg-success";
}).catch(() => {
  el.wasmStatus.textContent = "WebAssembly : erreur";
  el.wasmStatus.className   = "badge bg-danger";
});

function snapshot() {
  const s = ctx.getImageData(0, 0, canvas.width, canvas.height);
  return { data: new Uint8ClampedArray(s.data), width: s.width, height: s.height, recipe: { ...recipe } };
}

function restoreSnapshot(snap) {
  ctx.putImageData(new ImageData(snap.data, snap.width, snap.height), 0, 0);
  Object.assign(recipe, snap.recipe);
  syncSliders();
  refreshHistoryBtns();
}

function pushHistory() {
  if (!originalImageData) return;
  history.push(snapshot());
  refreshHistoryBtns();
}

function refreshHistoryBtns() {
  el.btnUndo.disabled = !history.canUndo;
  el.btnRedo.disabled = !history.canRedo;
}

function syncSliders() {
  el.sliderBrightness.value    = recipe.brightness;
  el.valBrightness.textContent = recipe.brightness;
  el.sliderContrast.value      = recipe.contrast;
  el.valContrast.textContent   = recipe.contrast;
}

function loadImage(file) {
  if (!file || !file.type.startsWith("image/")) return;
  uploadedFile = file;

  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.onload = () => {
      canvas.width  = img.width;
      canvas.height = img.height;
      ctx.drawImage(img, 0, 0);
      originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

      recipe.filter = null;
      recipe.brightness = 0;
      recipe.contrast   = 0;
      savedPhotoId      = null;

      syncSliders();
      history.clear();
      refreshHistoryBtns();

      el.canvasContainer.style.alignItems = "flex-start";
      el.dropHint.style.display = "none";
      canvas.style.display = "block";
      el.imgInfo.textContent = `${img.width} × ${img.height} px — ${(file.size / 1024).toFixed(1)} Ko`;

      [
        "btn-original", "btn-grayscale", "btn-sepia", "btn-invert", "btn-blur",
        "btn-export", "btn-save", "btn-zone-mode", "slider-brightness", "slider-contrast",
      ].forEach((id) => (document.getElementById(id).disabled = false));
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

async function save() {
  if (!originalImageData) return;
  el.btnSave.disabled    = true;
  el.btnSave.textContent = "Sauvegarde...";

  try {
    const formData = new FormData();
    formData.append("recipe", JSON.stringify(recipe));

    if (savedPhotoId) {
      formData.append("photo_id", savedPhotoId);
    } else {
      if (!uploadedFile) { el.btnSave.textContent = "Aucune image"; return; }
      formData.append("original", uploadedFile, uploadedFile.name);
    }

    const result = await fetch("save.php", { method: "POST", body: formData }).then((r) => r.json());

    if (result.success) {
      savedPhotoId           = result.id;
      el.btnSave.textContent = "Sauvegardé !";
      el.btnSave.classList.replace("btn-primary", "btn-success");
    } else {
      el.btnSave.textContent = "Erreur";
      el.btnSave.classList.replace("btn-primary", "btn-danger");
    }
  } catch {
    el.btnSave.textContent = "Erreur réseau";
  } finally {
    setTimeout(() => {
      el.btnSave.disabled   = false;
      el.btnSave.textContent = "Sauvegarder";
      el.btnSave.className   = "btn btn-primary btn-filter";
    }, 2500);
  }
}

document.getElementById("btn-original").addEventListener("click", () => {
  pushHistory();
  recipe.filter = null;
  recipe.brightness = 0;
  recipe.contrast   = 0;
  syncSliders();
  if (originalImageData) ctx.putImageData(originalImageData, 0, 0);
});

["grayscale", "sepia", "invert", "blur"].forEach((name) => {
  document.getElementById(`btn-${name}`).addEventListener("click", () => {
    pushHistory();
    recipe.filter = name;
    processor.applyRecipe(originalImageData, recipe);
  });
});

el.sliderBrightness.addEventListener("pointerdown", pushHistory);
el.sliderBrightness.addEventListener("input", (e) => {
  recipe.brightness            = parseInt(e.target.value);
  el.valBrightness.textContent = e.target.value;
  if (!zone.active) processor.applyRecipe(originalImageData, recipe);
});

el.sliderContrast.addEventListener("pointerdown", pushHistory);
el.sliderContrast.addEventListener("input", (e) => {
  recipe.contrast            = parseInt(e.target.value);
  el.valContrast.textContent = e.target.value;
  if (!zone.active) processor.applyRecipe(originalImageData, recipe);
});

document.getElementById("btn-export").addEventListener("click", () => {
  const link     = document.createElement("a");
  link.download  = "image_editee.png";
  link.href      = canvas.toDataURL("image/png");
  link.click();
});

el.btnSave.addEventListener("click", save);

el.btnZoneMode.addEventListener("click", () => {
  const active           = zone.toggle();
  el.btnZoneMode.textContent = active ? "Mode zone : ON" : "Mode zone : OFF";
  el.btnZoneMode.classList.toggle("btn-warning",         active);
  el.btnZoneMode.classList.toggle("btn-outline-warning", !active);
  el.zoneControls.style.display = active ? "block" : "none";
});

el.sliderZoneRadius.addEventListener("input", (e) => {
  zone.radius              = parseInt(e.target.value);
  el.valZoneRadius.textContent = zone.radius;
});

canvas.addEventListener("mousemove", (e) => {
  if (!zone.active) return;
  zone.moveCursor(e.clientX, e.clientY);
});

canvas.addEventListener("mouseleave", () => {
  if (zone.active) zone.hideCursor();
});

canvas.addEventListener("click", (e) => {
  if (!zone.active || !processor || !originalImageData) return;
  const { cx, cy } = zone.toCanvasCoords(e.clientX, e.clientY);
  pushHistory();
  processor.applyZoneFilter(recipe, cx, cy, zone.radius);
});

el.fileInput.addEventListener("change", (e) => loadImage(e.target.files[0]));

el.canvasContainer.addEventListener("dragover",  (e) => { e.preventDefault(); el.canvasContainer.style.borderColor = "#0d6efd"; });
el.canvasContainer.addEventListener("dragleave", ()  => { el.canvasContainer.style.borderColor = ""; });
el.canvasContainer.addEventListener("drop",      (e) => { e.preventDefault(); el.canvasContainer.style.borderColor = ""; loadImage(e.dataTransfer.files[0]); });

el.btnUndo.addEventListener("click", () => { const s = history.undo(snapshot()); if (s) restoreSnapshot(s); });
el.btnRedo.addEventListener("click", () => { const s = history.redo(snapshot()); if (s) restoreSnapshot(s); });

document.addEventListener("keydown", (e) => {
  if (e.ctrlKey && e.key === "z" && !e.shiftKey)                      { e.preventDefault(); const s = history.undo(snapshot()); if (s) restoreSnapshot(s); }
  if (e.ctrlKey && (e.key === "y" || (e.key === "z" && e.shiftKey))) { e.preventDefault(); const s = history.redo(snapshot()); if (s) restoreSnapshot(s); }
});
