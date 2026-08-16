function dateString(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function plusDays(date, days) {
  const value = new Date(date)
  value.setHours(12, 0, 0, 0)
  value.setDate(value.getDate() + days)
  return value
}

export function parseVoiceSearch(transcript, now = new Date()) {
  const text = String(transcript || '').trim()
  
  // Trích xuất số lượng khách, số phòng, số đêm
  const guests = text.match(/(?:cho\s+)?(\d{1,2})\s*(?:người|khách)/iu)
  const rooms = text.match(/(\d{1,2})\s*phòng/iu)
  const nights = text.match(/(\d{1,2})\s*đêm/iu)
  
  // Trích xuất ngày checkin dựa trên offset thời gian
  const offset = /(?:ngày kia|ngày mốt)/iu.test(text) ? 2 : /hôm nay/iu.test(text) ? 0 : 1
  const checkin = plusDays(now, offset)
  
  const adultsNum = guests ? Number(guests[1]) : 2
  const roomsNum = rooms ? Number(rooms[1]) : 1
  const stayNights = nights ? Number(nights[1]) : 1

  // Tiến hành làm sạch văn bản để trích xuất location và keyword loại phòng
  let cleanText = text
  
  // 1. Loại bỏ các phần số lượng và ngày tháng đã phân tích
  cleanText = cleanText.replace(/(?:cho\s+)?\d{1,2}\s*(?:người|khách)/iu, '')
  cleanText = cleanText.replace(/\d{1,2}\s*phòng/iu, '')
  cleanText = cleanText.replace(/\d{1,2}\s*đêm/iu, '')
  cleanText = cleanText.replace(/(?:hôm nay|ngày mai|ngày kia|ngày mốt)/iu, '')
  
  // 2. Loại bỏ các cụm từ đệm mở đầu thông dụng
  cleanText = cleanText.replace(/^(?:tìm\s+phòng|tìm\s+khách\s+sạn|tìm|đặt\s+phòng|đặt\s+khách\s+sạn|cho\s+tôi\s+xem|cho\s+tôi\s+tìm)\s+/iu, '')
  cleanText = cleanText.replace(/\s+(?:chỗ\s+nghỉ|khách\s+sạn|phòng)$/iu, '')
  
  // Chuẩn hóa khoảng trắng
  cleanText = cleanText.replace(/\s+/g, ' ').trim()

  let location = ''
  let keyword = ''

  // Danh sách các loại phòng phổ biến để tự động nhận dạng
  const roomTypes = ['deluxe', 'suite', 'standard', 'family', 'superior', 'penthouse', 'villa']

  // Bước A: Quét tìm keyword loại phòng qua cấu trúc "phòng + [loại phòng]"
  const roomMatch = cleanText.match(/\bphòng\s+([a-z0-9\-]+)/iu)
  if (roomMatch) {
    const candidate = roomMatch[1].toLowerCase()
    if (roomTypes.includes(candidate)) {
      keyword = roomMatch[1]
      cleanText = cleanText.replace(new RegExp(`\\bphòng\\s+${keyword}`, 'iu'), '')
    }
  }

  // Bước B: Nếu chưa tìm thấy, quét trực tiếp danh sách loại phòng trong cleanText
  if (!keyword) {
    for (const rt of roomTypes) {
      const regex = new RegExp(`\\b${rt}\\b`, 'iu')
      const match = cleanText.match(regex)
      if (match) {
        keyword = match[0]
        cleanText = cleanText.replace(regex, '')
        break
      }
    }
  }

  // Bước C: Phần còn lại của cleanText sau khi tách loại phòng chính là Địa điểm (Location)
  let locText = cleanText.trim()
  
  // Loại bỏ từ "ở", "tại", "phòng" ở đầu chuỗi một cách an toàn và triệt để
  while (true) {
    const lower = locText.toLowerCase()
    if (lower.startsWith('ở ')) {
      locText = locText.slice(2).trim()
    } else if (lower.startsWith('tại ')) {
      locText = locText.slice(4).trim()
    } else if (lower.startsWith('phòng ')) {
      locText = locText.slice(6).trim()
    } else {
      break
    }
  }

  // Loại bỏ từ "ở", "tại" ở cuối chuỗi
  while (true) {
    const lower = locText.toLowerCase()
    if (lower.endsWith(' ở')) {
      locText = locText.slice(0, -2).trim()
    } else if (lower.endsWith(' tại')) {
      locText = locText.slice(0, -4).trim()
    } else {
      break
    }
  }

  // Loại bỏ các cụm từ rỗng hoặc đệm không mang ý nghĩa địa điểm
  if (!/^(?:chỗ\s+nghỉ|nơi\s+nghỉ|khách\s+sạn)$/iu.test(locText)) {
    location = locText
  }

  // Fallback: Nếu không bóc tách được bằng cleanText, dùng regex cũ để bắt địa điểm đi kèm giới từ "ở/tại"
  if (!location) {
    const destinationMatch = text.match(/(?:^|\s)(?:ở|tại)\s+(.+?)(?=\s+cho\s+\d|\s+\d+\s*(?:người|khách|phòng|đêm)|\s+(?:hôm nay|ngày mai|ngày kia|ngày mốt)|$)/iu)
    if (destinationMatch) {
      location = destinationMatch[1].replace(/^(?:ở|tại|phòng)\s+/iu, '').trim()
    }
  }

  return {
    location: location,
    adults: adultsNum,
    rooms: roomsNum,
    checkin: dateString(checkin),
    checkout: dateString(plusDays(checkin, stayNights)),
    keyword: keyword,
  }
}
