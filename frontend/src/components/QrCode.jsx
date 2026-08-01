import { useEffect, useState } from 'react'
import QRCode from 'qrcode'

export default function QrCode({ value, size = 120, className = '' }) {
  const [src, setSrc] = useState(null)

  useEffect(() => {
    let active = true

    if (!value) {
      setSrc(null)
      return undefined
    }

    QRCode.toDataURL(value, { width: size * 4, margin: 1 })
      .then((url) => { if (active) setSrc(url) })
      .catch(() => { if (active) setSrc(null) })

    return () => { active = false }
  }, [value, size])

  if (!src) return null

  return <img src={src} alt="QR Code" width={size} height={size} className={className} />
}
