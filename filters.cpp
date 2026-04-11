#include <cstdint>
#include <emscripten.h>

extern "C" {

EMSCRIPTEN_KEEPALIVE
void apply_filter(uint8_t* data, int width, int height) {
    (void)data;
    (void)width;
    (void)height;
}

}