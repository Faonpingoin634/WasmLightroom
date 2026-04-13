#include <cstdint>
#include <algorithm>
#include <cmath>
#include <emscripten.h>

extern "C" {

EMSCRIPTEN_KEEPALIVE
void apply_grayscale(uint8_t* data, int width, int height) {
    int numPixels = width * height;
    for (int i = 0; i < numPixels; i++) {
        uint8_t r = data[i * 4];
        uint8_t g = data[i * 4 + 1];
        uint8_t b = data[i * 4 + 2];
        uint8_t gray = (uint8_t)(0.299f * r + 0.587f * g + 0.114f * b);
        data[i * 4]     = gray;
        data[i * 4 + 1] = gray;
        data[i * 4 + 2] = gray;
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_sepia(uint8_t* data, int width, int height) {
    int numPixels = width * height;
    for (int i = 0; i < numPixels; i++) {
        float r = data[i * 4];
        float g = data[i * 4 + 1];
        float b = data[i * 4 + 2];
        data[i * 4]     = (uint8_t)std::min(255.0f, r * 0.393f + g * 0.769f + b * 0.189f);
        data[i * 4 + 1] = (uint8_t)std::min(255.0f, r * 0.349f + g * 0.686f + b * 0.168f);
        data[i * 4 + 2] = (uint8_t)std::min(255.0f, r * 0.272f + g * 0.534f + b * 0.131f);
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_invert(uint8_t* data, int width, int height) {
    int numPixels = width * height;
    for (int i = 0; i < numPixels; i++) {
        data[i * 4]     = 255 - data[i * 4];
        data[i * 4 + 1] = 255 - data[i * 4 + 1];
        data[i * 4 + 2] = 255 - data[i * 4 + 2];
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_blur(uint8_t* data, int width, int height) {
    const int kernel[3][3] = {{1,2,1},{2,4,2},{1,2,1}};
    const int kernelSum = 16;
    uint8_t* tmp = new uint8_t[width * height * 4];
    for (int i = 0; i < width * height * 4; i++) tmp[i] = data[i];
    for (int y = 1; y < height - 1; y++) {
        for (int x = 1; x < width - 1; x++) {
            int r = 0, g = 0, b = 0;
            for (int ky = -1; ky <= 1; ky++) {
                for (int kx = -1; kx <= 1; kx++) {
                    int idx = ((y + ky) * width + (x + kx)) * 4;
                    int w = kernel[ky + 1][kx + 1];
                    r += tmp[idx]     * w;
                    g += tmp[idx + 1] * w;
                    b += tmp[idx + 2] * w;
                }
            }
            int idx = (y * width + x) * 4;
            data[idx]     = (uint8_t)(r / kernelSum);
            data[idx + 1] = (uint8_t)(g / kernelSum);
            data[idx + 2] = (uint8_t)(b / kernelSum);
        }
    }
    delete[] tmp;
}

EMSCRIPTEN_KEEPALIVE
void apply_brightness(uint8_t* data, int width, int height, int delta) {
    int numPixels = width * height;
    for (int i = 0; i < numPixels; i++) {
        data[i * 4]     = (uint8_t)std::min(255, std::max(0, (int)data[i * 4]     + delta));
        data[i * 4 + 1] = (uint8_t)std::min(255, std::max(0, (int)data[i * 4 + 1] + delta));
        data[i * 4 + 2] = (uint8_t)std::min(255, std::max(0, (int)data[i * 4 + 2] + delta));
    }
}

EMSCRIPTEN_KEEPALIVE
void apply_contrast(uint8_t* data, int width, int height, int value) {
    float factor = (259.0f * (value + 255)) / (255.0f * (259 - value));
    int numPixels = width * height;
    for (int i = 0; i < numPixels; i++) {
        data[i * 4]     = (uint8_t)std::min(255.0f, std::max(0.0f, factor * (data[i * 4]     - 128) + 128));
        data[i * 4 + 1] = (uint8_t)std::min(255.0f, std::max(0.0f, factor * (data[i * 4 + 1] - 128) + 128));
        data[i * 4 + 2] = (uint8_t)std::min(255.0f, std::max(0.0f, factor * (data[i * 4 + 2] - 128) + 128));
    }
}

}
