const SPECIAL_CHARS = "@$!%*#?&._-";
const LOWERCASE = "abcdefghjkmnpqrstuvwxyz";
const UPPERCASE = "ABCDEFGHJKLMNPQRSTUVWXYZ";
const DIGITS = "23456789";

function randomFrom(set) {
  return set[Math.floor(Math.random() * set.length)];
}

export function generateStrongPassword(length = 16) {
  const requiredSets = [UPPERCASE, LOWERCASE, DIGITS, SPECIAL_CHARS];
  const allChars = UPPERCASE + LOWERCASE + DIGITS + SPECIAL_CHARS;

  const chars = requiredSets.map(randomFrom);

  while (chars.length < length) {
    chars.push(randomFrom(allChars));
  }

  for (let i = chars.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }

  return chars.join("");
}

export const PASSWORD_REQUIREMENTS = [
  { key: "min", label: "At least 8 characters", test: (value) => value.length >= 8 },
  { key: "upper", label: "One uppercase letter", test: (value) => /[A-Z]/.test(value) },
  { key: "lower", label: "One lowercase letter", test: (value) => /[a-z]/.test(value) },
  { key: "number", label: "One number", test: (value) => /[0-9]/.test(value) },
  {
    key: "special",
    label: "One special character (@ $ ! % * # ? & . _ -)",
    test: (value) => /[@$!%*#?&._-]/.test(value),
  },
];
