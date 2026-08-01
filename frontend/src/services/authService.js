import api from "@/services/api";

const AUTH_TOKEN_KEY = "auth_token";
const AUTH_USER_KEY = "auth_user";

export function getStoredToken() {
  return localStorage.getItem(AUTH_TOKEN_KEY);
}

export function getStoredUser() {
  const raw = localStorage.getItem(AUTH_USER_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function setAuthSession(token, user) {
  localStorage.setItem(AUTH_TOKEN_KEY, token);
  localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
}

export function clearAuthSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY);
  localStorage.removeItem(AUTH_USER_KEY);
}

export async function login(credentials) {
  const { data } = await api.post("/v1/auth/login", credentials);

  return data;
}

export async function register(payload) {
  const { data } = await api.post("/v1/auth/register", {
    name: payload.name,
    email: payload.email,
    password: payload.password,
    password_confirmation: payload.password_confirmation,
    phone: payload.phone,
    verification_channel: payload.verification_channel,
  });

  return data;
}

export async function verifyEmail(payload) {
  const { data } = await api.post("/v1/auth/verify", {
    email: payload.email,
    code: payload.code,
  });

  return data;
}

export async function resendVerification(payload) {
  const { data } = await api.post("/v1/auth/resend-verification", {
    email: payload.email,
    verification_channel: payload.verification_channel,
  });

  return data;
}

export async function logout() {
  const token = getStoredToken();

  // If we don't have a token locally, there is nothing to invalidate on the server.
  if (!token) {
    clearAuthSession();
    return;
  }

  try {
    await api.post("/v1/auth/logout");
  } catch (err) {
    // After a session expires (or the token is already invalid), Sanctum may return 401.
    // Treat this as a local logout success so the UI can proceed to /login cleanly.
    if (err?.response?.status !== 401) {
      throw err;
    }
  } finally {
    clearAuthSession();
  }
}

export async function fetchCurrentUser() {
  try {
    const { data } = await api.get("/v1/auth/me");
    return data;
  } catch (err) {
    // If Sanctum returns 401 during bootstrap (expired/absent token),
    // treat as logged out and avoid noisy console errors.
    if (err?.response?.status === 401) {
      clearAuthSession();
      return null;
    }
    throw err;
  }
}
