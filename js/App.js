import { WasmLoader } from './WasmLoader.js';
import { ImageHistory } from './ImageHistory.js';
import { ImageEditor } from './ImageEditor.js';
import { ZoneMode } from './ZoneMode.js';

class App {
  #recipe = { filter: null, brightness: 0, contrast: 0 };
  #uploadedFile = null;
  #savedPhotoId = null;

  #canvas = document.getElementById('main-canvas');
  #history = new ImageHistory(20);
  #loader = new WasmLoader();
  #editor = null;
  #zone = null;

  #el = {
    wasmStatus:      document.getElementById('wasm-status'),
    fileInput:       document.getElementById('file-input'),
    dropHint:        document.getElementById('drop-hint'),
    canvasContainer: document.getElementById('canvas-container'),
    imgInfo:         document.getElementById('img-info'),
    btnUndo:         document.getElementById('btn-undo'),
    btnRedo:         document.getElementById('btn-redo'),
    btnOriginal:     document.getElementById('btn-original'),
    btnExport:       document.getElementById('btn-export'),
    btnSave:         document.getElementById('btn-save'),
    btnZoneMode:     document.getElementById('btn-zone-mode'),
    sliderBrightness: document.getElementById('slider-brightness'),
    sliderContrast:   document.getElementById('slider-contrast'),
    valBrightness:    document.getElementById('val-brightness'),
    valContrast:      document.getElementById('val-contrast'),
    sliderZoneRadius: document.getElementById('slider-zone-radius'),
    valZoneRadius:    document.getElementById('val-zone-radius'),
    zoneControls:     document.getElementById('zone-controls'),
    zoneCursor:       document.getElementById('zone-cursor'),
  };

  async init() {
    this.#zone = new ZoneMode(this.#canvas, this.#el.zoneCursor);
    this.#bindEvents();
    await this.#initWasm();
  }

  async #initWasm() {
    try {
      const wasm = await this.#loader.load();
      this.#editor = new ImageEditor(this.#canvas, wasm);
      this.#el.wasmStatus.textContent = 'WebAssembly : prêt';
      this.#el.wasmStatus.className = 'badge bg-success';
    } catch (err) {
      this.#el.wasmStatus.textContent = 'WebAssembly : erreur';
      this.#el.wasmStatus.className = 'badge bg-danger';
    }
  }

  async #handleFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    this.#uploadedFile = file;

    const { width, height, size } = await this.#editor.loadImage(file);

    this.#recipe = { filter: null, brightness: 0, contrast: 0 };
    this.#savedPhotoId = null;
    this.#history.clear();

    this.#el.sliderBrightness.value = 0;
    this.#el.valBrightness.textContent = '0';
    this.#el.sliderContrast.value = 0;
    this.#el.valContrast.textContent = '0';
    this.#el.canvasContainer.style.alignItems = 'flex-start';
    this.#el.dropHint.style.display = 'none';
    this.#canvas.style.display = 'block';
    this.#el.imgInfo.textContent = `${width} × ${height} px — ${(size / 1024).toFixed(1)} Ko`;

    this.#updateHistoryBtns();
    this.#enableControls();
  }

  #enableControls() {
    [
      'btn-original', 'btn-grayscale', 'btn-sepia', 'btn-invert', 'btn-blur',
      'btn-export', 'btn-save', 'btn-zone-mode', 'slider-brightness', 'slider-contrast',
    ].forEach((id) => (document.getElementById(id).disabled = false));
  }

  #snapshot() {
    return this.#editor.snapshot(this.#recipe);
  }

  #pushHistory() {
    if (!this.#editor?.originalImageData) return;
    this.#history.push(this.#snapshot());
    this.#updateHistoryBtns();
  }

  #updateHistoryBtns() {
    this.#el.btnUndo.disabled = !this.#history.canUndo;
    this.#el.btnRedo.disabled = !this.#history.canRedo;
  }

  #syncSliders() {
    this.#el.sliderBrightness.value = this.#recipe.brightness;
    this.#el.valBrightness.textContent = this.#recipe.brightness;
    this.#el.sliderContrast.value = this.#recipe.contrast;
    this.#el.valContrast.textContent = this.#recipe.contrast;
  }

  #undo() {
    const prev = this.#history.undo(this.#snapshot());
    if (!prev) return;
    this.#editor.restore(prev);
    Object.assign(this.#recipe, prev.recipe);
    this.#syncSliders();
    this.#updateHistoryBtns();
  }

  #redo() {
    const next = this.#history.redo(this.#snapshot());
    if (!next) return;
    this.#editor.restore(next);
    Object.assign(this.#recipe, next.recipe);
    this.#syncSliders();
    this.#updateHistoryBtns();
  }

  async #save() {
    if (!this.#editor?.originalImageData) return;

    const btn = this.#el.btnSave;
    btn.disabled = true;
    btn.textContent = 'Sauvegarde...';

    try {
      const formData = new FormData();
      formData.append('recipe', JSON.stringify(this.#recipe));

      if (this.#savedPhotoId) {
        formData.append('photo_id', this.#savedPhotoId);
      } else {
        if (!this.#uploadedFile) { btn.textContent = 'Aucune image'; return; }
        formData.append('original', this.#uploadedFile, this.#uploadedFile.name);
      }

      const response = await fetch('save.php', { method: 'POST', body: formData });
      const result = await response.json();

      if (result.success) {
        this.#savedPhotoId = result.id;
        btn.textContent = 'Sauvegardé !';
        btn.classList.replace('btn-primary', 'btn-success');
      } else {
        btn.textContent = 'Erreur';
        btn.classList.replace('btn-primary', 'btn-danger');
      }
    } catch {
      btn.textContent = 'Erreur réseau';
    } finally {
      setTimeout(() => {
        btn.disabled = false;
        btn.textContent = 'Sauvegarder';
        btn.className = 'btn btn-primary btn-filter';
      }, 2500);
    }
  }

  #bindEvents() {
    this.#el.fileInput.addEventListener('change', (e) => this.#handleFile(e.target.files[0]));

    const container = this.#el.canvasContainer;
    container.addEventListener('dragover', (e) => {
      e.preventDefault();
      container.style.borderColor = '#000';
    });
    container.addEventListener('dragleave', () => {
      container.style.borderColor = '';
    });
    container.addEventListener('drop', (e) => {
      e.preventDefault();
      container.style.borderColor = '';
      this.#handleFile(e.dataTransfer.files[0]);
    });

    this.#el.btnUndo.addEventListener('click', () => this.#undo());
    this.#el.btnRedo.addEventListener('click', () => this.#redo());

    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey && e.key === 'z' && !e.shiftKey) { e.preventDefault(); this.#undo(); }
      if (e.ctrlKey && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); this.#redo(); }
    });

    this.#el.btnOriginal.addEventListener('click', () => {
      this.#pushHistory();
      this.#recipe.filter = null;
      this.#recipe.brightness = 0;
      this.#recipe.contrast = 0;
      this.#syncSliders();
      this.#editor.restoreOriginal();
    });

    ['grayscale', 'sepia', 'invert', 'blur'].forEach((name) => {
      document.getElementById(`btn-${name}`).addEventListener('click', () => {
        this.#pushHistory();
        this.#recipe.filter = name;
        this.#editor.applyRecipe(this.#recipe);
      });
    });

    this.#el.sliderBrightness.addEventListener('pointerdown', () => this.#pushHistory());
    this.#el.sliderBrightness.addEventListener('input', (e) => {
      this.#recipe.brightness = parseInt(e.target.value);
      this.#el.valBrightness.textContent = e.target.value;
      if (!this.#zone.active) this.#editor.applyRecipe(this.#recipe);
    });

    this.#el.sliderContrast.addEventListener('pointerdown', () => this.#pushHistory());
    this.#el.sliderContrast.addEventListener('input', (e) => {
      this.#recipe.contrast = parseInt(e.target.value);
      this.#el.valContrast.textContent = e.target.value;
      if (!this.#zone.active) this.#editor.applyRecipe(this.#recipe);
    });

    this.#el.btnExport.addEventListener('click', () => {
      const link = document.createElement('a');
      link.download = 'image_editee.png';
      link.href = this.#editor.exportPng();
      link.click();
    });

    this.#el.btnSave.addEventListener('click', () => this.#save());

    this.#el.btnZoneMode.addEventListener('click', () => {
      const active = this.#zone.toggle();
      this.#el.btnZoneMode.textContent = active ? 'Mode zone : ON' : 'Mode zone : OFF';
      this.#el.btnZoneMode.classList.toggle('btn-warning', active);
      this.#el.btnZoneMode.classList.toggle('btn-outline-warning', !active);
      this.#el.zoneControls.style.display = active ? 'block' : 'none';
    });

    this.#el.sliderZoneRadius.addEventListener('input', (e) => {
      this.#zone.radius = parseInt(e.target.value);
      this.#el.valZoneRadius.textContent = this.#zone.radius;
    });

    this.#canvas.addEventListener('mousemove', (e) => {
      if (!this.#zone.active) return;
      this.#el.zoneCursor.style.display = 'block';
      this.#zone.updateCursor(e.clientX, e.clientY);
    });

    this.#canvas.addEventListener('mouseleave', () => {
      if (this.#zone.active) this.#el.zoneCursor.style.display = 'none';
    });

    this.#canvas.addEventListener('click', (e) => {
      if (!this.#zone.active || !this.#editor?.originalImageData) return;
      const { x, y } = this.#zone.getCanvasCoords(e.clientX, e.clientY);
      this.#pushHistory();
      this.#editor.applyZoneFilter(x, y, this.#zone.radius, this.#recipe);
    });
  }
}

new App().init();
