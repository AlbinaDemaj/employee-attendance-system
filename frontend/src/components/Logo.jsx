import { useId } from 'react'

/**
 * Shenja e sistemit: një orë me një hapësirë në unazë dhe një pikë që shënon
 * fillimin e turnit. E njëjta formë përdoret te favicon-i (public/favicon.svg),
 * ndaj çdo ndryshim këtu duhet pasqyruar edhe atje.
 */

export function LogoMark({ size = 36, className = '' }) {
  // Gradientët në SVG janë globalë për faqen, ndaj çdo shenjë merr id-në e vet.
  // useId kthen shenja si «r0», që nuk hyjnë në një referencë url(#…).
  const id = `logo-grad-${useId().replace(/[^a-zA-Z0-9]/g, '')}`

  return (
    <svg
      className={`logo-mark ${className}`}
      width={size}
      height={size}
      viewBox="0 0 48 48"
      role="img"
      aria-label="Prezenca"
    >
      <defs>
        <linearGradient id={id} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#6366f1" />
          <stop offset="1" stopColor="#8b5cf6" />
        </linearGradient>
      </defs>
      <rect width="48" height="48" rx="13" fill={`url(#${id})`} />
      <path
        d="M35.28 19.90 A12 12 0 1 1 28.10 12.72"
        fill="none"
        stroke="#fff"
        strokeWidth="3.1"
        strokeLinecap="round"
        opacity=".95"
      />
      <circle cx="32.49" cy="15.51" r="2.7" fill="#fff" />
      <path
        d="M24 16.6 V24 h5.4"
        fill="none"
        stroke="#fff"
        strokeWidth="3.1"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

/** Shenja bashkë me emrin — dhe një etiketë kur jemi te pjesa e platformës. */
export default function Logo({ size = 36, tag = null }) {
  return (
    <span className="logo">
      <LogoMark size={size} />
      <span className="logo-text">
        <span className="logo-name">Prezenca</span>
        {tag && <span className="logo-tag">{tag}</span>}
      </span>
    </span>
  )
}
