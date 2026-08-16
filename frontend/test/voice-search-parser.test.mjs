import test from 'node:test'
import assert from 'node:assert/strict'
import { parseVoiceSearch } from '../src/voiceSearchParser.js'

test('parses the Vietnamese demo Deluxe request', () => {
  const result = parseVoiceSearch('Tìm phòng Deluxe cho 2 người ngày mai', new Date('2026-08-15T10:00:00+07:00'))

  assert.deepEqual(result, {
    location: '',
    adults: 2,
    rooms: 1,
    checkin: '2026-08-16',
    checkout: '2026-08-17',
    keyword: 'Deluxe',
  })
})

test('parses a destination and preserves room keywords', () => {
  const result = parseVoiceSearch('Tìm phòng suite ở Đà Lạt cho 3 người hôm nay', new Date('2026-08-15T10:00:00+07:00'))

  assert.equal(result.location, 'Đà Lạt')
  assert.equal(result.adults, 3)
  assert.equal(result.rooms, 1)
  assert.equal(result.checkin, '2026-08-15')
  assert.equal(result.checkout, '2026-08-16')
  assert.equal(result.keyword, 'suite')
})

test('parses room count and stay length from common phrases', () => {
  const result = parseVoiceSearch('Tìm phòng Deluxe tại Nha Trang cho 4 khách 2 phòng ngày mốt 3 đêm', new Date('2026-08-15T10:00:00+07:00'))

  assert.deepEqual(result, {
    location: 'Nha Trang',
    adults: 4,
    rooms: 2,
    checkin: '2026-08-17',
    checkout: '2026-08-20',
    keyword: 'Deluxe',
  })
})

test('returns safe defaults for an unstructured transcript', () => {
  const result = parseVoiceSearch('Cho tôi xem chỗ nghỉ', new Date('2026-08-15T10:00:00+07:00'))

  assert.equal(result.adults, 2)
  assert.equal(result.rooms, 1)
  assert.equal(result.checkin, '2026-08-16')
  assert.equal(result.checkout, '2026-08-17')
  assert.equal(result.keyword, '')
})
