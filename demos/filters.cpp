#include <cstdint>
#include <cstring>
#include <algorithm>
#include <emscripten.h>

static inline uint8_t clamp8(int v) {
    return v < 0 ? 0 : (v > 255 ? 255 : static_cast<uint8_t>(v));
}

static inline uint32_t pack(uint8_t r, uint8_t g, uint8_t b, uint32_t a) {
    return a | (static_cast<uint32_t>(b) << 16) | (static_cast<uint32_t>(g) << 8) | r;
}

extern "C" {

EMSCRIPTEN_KEEPALIVE
void apply_grayscale(uint8_t* data, int width, int height) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int n = width * height;
    for (int i = 0; i < n; i++) {
        uint32_t p = px[i];
        uint8_t gray = static_cast<uint8_t>(
            0.299f * (p & 0xFF) + 0.587f * ((p >> 8) & 0xFF) + 0.114f * ((p >> 16) & 0xFF)
        );
        px[i] = pack(gray, gray, gray, p & 0xFF000000u);
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_sepia(uint8_t* data, int width, int height) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int n = width * height;
    for (int i = 0; i < n; i++) {
        uint32_t p = px[i];
        float r = p & 0xFF, g = (p >> 8) & 0xFF, b = (p >> 16) & 0xFF;
        px[i] = pack(
            static_cast<uint8_t>(std::min(255.0f, r * 0.393f + g * 0.769f + b * 0.189f)),
            static_cast<uint8_t>(std::min(255.0f, r * 0.349f + g * 0.686f + b * 0.168f)),
            static_cast<uint8_t>(std::min(255.0f, r * 0.272f + g * 0.534f + b * 0.131f)),
            p & 0xFF000000u
        );
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_invert(uint8_t* data, int width, int height) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int n = width * height;
    for (int i = 0; i < n; i++) {
        px[i] ^= 0x00FFFFFFu;
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_blur(uint8_t* data, int width, int height) {
    const int kernel[3][3] = {{1,2,1},{2,4,2},{1,2,1}};
    uint32_t* px  = reinterpret_cast<uint32_t*>(data);
    uint32_t* tmp = new uint32_t[width * height];
    std::memcpy(tmp, px, width * height * 4);
    for (int y = 1; y < height - 1; y++) {
        for (int x = 1; x < width - 1; x++) {
            int r = 0, g = 0, b = 0;
            for (int ky = -1; ky <= 1; ky++) {
                for (int kx = -1; kx <= 1; kx++) {
                    uint32_t p = tmp[(y + ky) * width + (x + kx)];
                    int w = kernel[ky + 1][kx + 1];
                    r += (p & 0xFF) * w;
                    g += ((p >> 8)  & 0xFF) * w;
                    b += ((p >> 16) & 0xFF) * w;
                }
            }
            uint32_t src = tmp[y * width + x];
            px[y * width + x] = pack(r / 16, g / 16, b / 16, src & 0xFF000000u);
        }
    }
    delete[] tmp;
}

EMSCRIPTEN_KEEPALIVE
void apply_brightness(uint8_t* data, int width, int height, int delta) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int n = width * height;
    for (int i = 0; i < n; i++) {
        uint32_t p = px[i];
        px[i] = pack(
            clamp8((p & 0xFF)         + delta),
            clamp8(((p >> 8)  & 0xFF) + delta),
            clamp8(((p >> 16) & 0xFF) + delta),
            p & 0xFF000000u
        );
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_contrast(uint8_t* data, int width, int height, int value) {
    float factor = (259.0f * (value + 255)) / (255.0f * (259 - value));
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int n = width * height;
    for (int i = 0; i < n; i++) {
        uint32_t p = px[i];
        px[i] = pack(
            clamp8(static_cast<int>(factor * ((p & 0xFF)         - 128) + 128)),
            clamp8(static_cast<int>(factor * (((p >> 8)  & 0xFF) - 128) + 128)),
            clamp8(static_cast<int>(factor * (((p >> 16) & 0xFF) - 128) + 128)),
            p & 0xFF000000u
        );
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_grayscale_zone(uint8_t* data, int width, int height, int cx, int cy, int radius) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int r2 = radius * radius;
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            uint32_t p = px[y * width + x];
            uint8_t gray = static_cast<uint8_t>(
                0.299f * (p & 0xFF) + 0.587f * ((p >> 8) & 0xFF) + 0.114f * ((p >> 16) & 0xFF)
            );
            px[y * width + x] = pack(gray, gray, gray, p & 0xFF000000u);
        }
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_sepia_zone(uint8_t* data, int width, int height, int cx, int cy, int radius) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int r2 = radius * radius;
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            uint32_t p = px[y * width + x];
            float r = p & 0xFF, g = (p >> 8) & 0xFF, b = (p >> 16) & 0xFF;
            px[y * width + x] = pack(
                static_cast<uint8_t>(std::min(255.0f, r * 0.393f + g * 0.769f + b * 0.189f)),
                static_cast<uint8_t>(std::min(255.0f, r * 0.349f + g * 0.686f + b * 0.168f)),
                static_cast<uint8_t>(std::min(255.0f, r * 0.272f + g * 0.534f + b * 0.131f)),
                p & 0xFF000000u
            );
        }
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_invert_zone(uint8_t* data, int width, int height, int cx, int cy, int radius) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int r2 = radius * radius;
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            px[y * width + x] ^= 0x00FFFFFFu;
        }
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_blur_zone(uint8_t* data, int width, int height, int cx, int cy, int radius) {
    const int kernel[3][3] = {{1,2,1},{2,4,2},{1,2,1}};
    uint32_t* px  = reinterpret_cast<uint32_t*>(data);
    uint32_t* tmp = new uint32_t[width * height];
    std::memcpy(tmp, px, width * height * 4);
    int r2 = radius * radius;
    for (int y = 1; y < height - 1; y++) {
        for (int x = 1; x < width - 1; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            int r = 0, g = 0, b = 0;
            for (int ky = -1; ky <= 1; ky++) {
                for (int kx = -1; kx <= 1; kx++) {
                    uint32_t p = tmp[(y + ky) * width + (x + kx)];
                    int w = kernel[ky + 1][kx + 1];
                    r += (p & 0xFF) * w;
                    g += ((p >> 8)  & 0xFF) * w;
                    b += ((p >> 16) & 0xFF) * w;
                }
            }
            uint32_t src = tmp[y * width + x];
            px[y * width + x] = pack(r / 16, g / 16, b / 16, src & 0xFF000000u);
        }
    }
    delete[] tmp;
}

EMSCRIPTEN_KEEPALIVE
void apply_brightness_zone(uint8_t* data, int width, int height, int delta, int cx, int cy, int radius) {
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int r2 = radius * radius;
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            uint32_t p = px[y * width + x];
            px[y * width + x] = pack(
                clamp8((p & 0xFF)         + delta),
                clamp8(((p >> 8)  & 0xFF) + delta),
                clamp8(((p >> 16) & 0xFF) + delta),
                p & 0xFF000000u
            );
        }
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_contrast_zone(uint8_t* data, int width, int height, int value, int cx, int cy, int radius) {
    float factor = (259.0f * (value + 255)) / (255.0f * (259 - value));
    uint32_t* px = reinterpret_cast<uint32_t*>(data);
    int r2 = radius * radius;
    for (int y = 0; y < height; y++) {
        for (int x = 0; x < width; x++) {
            int dx = x - cx, dy = y - cy;
            if (dx * dx + dy * dy > r2) continue;
            uint32_t p = px[y * width + x];
            px[y * width + x] = pack(
                clamp8(static_cast<int>(factor * ((p & 0xFF)         - 128) + 128)),
                clamp8(static_cast<int>(factor * (((p >> 8)  & 0xFF) - 128) + 128)),
                clamp8(static_cast<int>(factor * (((p >> 16) & 0xFF) - 128) + 128)),
                p & 0xFF000000u
            );
        }
    }
}

}
