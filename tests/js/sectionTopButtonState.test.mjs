import test from 'node:test'
import assert from 'node:assert/strict'

import { shouldShowSectionTopButton } from '../../resources/js/utils/sectionTopButtonState.mjs'

test('shouldShowSectionTopButton returns false when page does not have enough scrollable area', () => {
    assert.equal(
        shouldShowSectionTopButton({
            scrollTop: 900,
            scrollHeight: 980,
            viewportHeight: 900,
            threshold: 320,
        }),
        false,
    )
})

test('shouldShowSectionTopButton returns false until threshold is crossed', () => {
    assert.equal(
        shouldShowSectionTopButton({
            scrollTop: 320,
            scrollHeight: 2400,
            viewportHeight: 900,
            threshold: 320,
        }),
        false,
    )
})

test('shouldShowSectionTopButton returns true once scroll threshold is exceeded on a long page', () => {
    assert.equal(
        shouldShowSectionTopButton({
            scrollTop: 321,
            scrollHeight: 2400,
            viewportHeight: 900,
            threshold: 320,
        }),
        true,
    )
})

test('shouldShowSectionTopButton tolerates missing and non-numeric input', () => {
    assert.equal(
        shouldShowSectionTopButton({
            scrollTop: '400',
            scrollHeight: '2500',
            viewportHeight: '1000',
            threshold: '350',
        }),
        true,
    )

    assert.equal(shouldShowSectionTopButton(), false)
})
