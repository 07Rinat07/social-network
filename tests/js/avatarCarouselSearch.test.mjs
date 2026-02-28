import test from 'node:test'
import assert from 'node:assert/strict'

import {
    normalizeAvatarSearchValue,
    resolveAvatarCarouselSearch,
} from '../../resources/js/utils/avatarCarouselSearch.mjs'

const users = [
    {
        id: 1,
        display_name: 'Test User 1',
        nickname: 'demo_user_01',
    },
    {
        id: 2,
        display_name: 'Test User 10',
        nickname: 'demo_user_010',
    },
    {
        id: 3,
        display_name: 'Анна Петрова',
        nickname: 'anna.pet',
    },
    {
        id: 4,
        display_name: 'User 6',
        nickname: 'user_6',
    },
    {
        id: 5,
        display_name: 'User 67',
        nickname: 'user_67',
    },
    {
        id: 6,
        display_name: 'Иван 6',
        nickname: 'иван_6',
    },
    {
        id: 7,
        display_name: 'Иван 67',
        nickname: 'иван_67',
    },
]

test('normalizeAvatarSearchValue trims spaces and leading @ symbols', () => {
    assert.equal(normalizeAvatarSearchValue('  @Demo_User_01  ', 'en-US'), 'demo_user_01')
})

test('resolveAvatarCarouselSearch returns only exact matches when exact nickname exists', () => {
    const result = resolveAvatarCarouselSearch(users, '@demo_user_01', 'en-US')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 1)
})

test('resolveAvatarCarouselSearch treats separator-normalized equality as an exact match', () => {
    const result = resolveAvatarCarouselSearch(users, 'demo user 01', 'en-US')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 1)
})

test('resolveAvatarCarouselSearch treats spaced and unspaced letter-digit tokens as an exact match', () => {
    const result = resolveAvatarCarouselSearch(users, 'user6', 'en-US')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 4)
})

test('resolveAvatarCarouselSearch treats underscore and spaced letter-digit tokens as an exact match', () => {
    const result = resolveAvatarCarouselSearch(users, 'user_6', 'en-US')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 4)
})

test('resolveAvatarCarouselSearch falls back to similar matches when only partial coincidence exists', () => {
    const result = resolveAvatarCarouselSearch(users, 'user', 'en-US')

    assert.equal(result.mode, 'similar')
    assert.equal(result.items.length, 4)
    assert.deepEqual(result.items.map((entry) => entry.id), [1, 2, 4, 5])
})

test('resolveAvatarCarouselSearch does not confuse user6 with user67', () => {
    const result = resolveAvatarCarouselSearch(users, 'user 6', 'en-US')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 4)
})

test('resolveAvatarCarouselSearch returns no results when neither exact nor similar users exist', () => {
    const result = resolveAvatarCarouselSearch(users, 'несуществующий пользователь', 'ru-RU')

    assert.equal(result.mode, 'none')
    assert.deepEqual(result.items, [])
})

test('resolveAvatarCarouselSearch treats Russian spaced and unspaced letter-digit tokens as an exact match', () => {
    const result = resolveAvatarCarouselSearch(users, 'иван6', 'ru-RU')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 6)
})

test('resolveAvatarCarouselSearch treats Russian underscore and spaced letter-digit tokens as an exact match', () => {
    const result = resolveAvatarCarouselSearch(users, 'иван_6', 'ru-RU')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 6)
})

test('resolveAvatarCarouselSearch returns multiple similar Russian variants for partial query', () => {
    const result = resolveAvatarCarouselSearch(users, 'иван', 'ru-RU')

    assert.equal(result.mode, 'similar')
    assert.equal(result.items.length, 2)
    assert.deepEqual(result.items.map((entry) => entry.id), [6, 7])
})

test('resolveAvatarCarouselSearch does not confuse Russian exact match with longer numeric suffix', () => {
    const result = resolveAvatarCarouselSearch(users, 'иван 6', 'ru-RU')

    assert.equal(result.mode, 'exact')
    assert.equal(result.items.length, 1)
    assert.equal(result.items[0].id, 6)
})
