export function shouldShowSectionTopButton({
    scrollTop = 0,
    scrollHeight = 0,
    viewportHeight = 0,
    threshold = 320,
} = {}) {
    const safeScrollTop = Number(scrollTop) || 0
    const safeScrollHeight = Number(scrollHeight) || 0
    const safeViewportHeight = Number(viewportHeight) || 0
    const safeThreshold = Number(threshold) || 0

    if (safeScrollHeight - safeViewportHeight <= 120) {
        return false
    }

    return safeScrollTop > safeThreshold
}
