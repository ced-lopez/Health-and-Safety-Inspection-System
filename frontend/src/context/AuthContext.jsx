import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  clearAuthSession,
  fetchCurrentUser,
  getStoredToken,
  getStoredUser,
  login as loginRequest,
  logout as logoutRequest,
  register as registerRequest,
  resendVerification as resendVerificationRequest,
  setAuthSession,
  verifyEmail as verifyEmailRequest,
} from "@/services/authService";
import { canAccessModule } from "@/utils/permissions";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(getStoredUser);
  // If we already have a stored user/token, don't block the UI on /auth/me.
  // We'll refresh user in the background.
  const [loading, setLoading] = useState(!getStoredToken());

  const bootstrap = useCallback(async () => {
    const token = getStoredToken();

    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const response = await fetchCurrentUser();
      setUser(response.data);
      localStorage.setItem("auth_user", JSON.stringify(response.data));
    } catch {
      clearAuthSession();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    bootstrap();
  }, [bootstrap]);

  const login = useCallback(async (credentials) => {
    const response = await loginRequest(credentials);

    // Unverified accounts receive a verification_required response with no token,
    // so no session is created until the email is verified.
    if (response.data?.token) {
      setAuthSession(response.data.token, response.data.user);
      setUser(response.data.user);
    }

    return response;
  }, []);

  const register = useCallback(async (payload) => {
    const response = await registerRequest(payload);

    // Registration requires email verification before a session token is issued.
    if (response.data?.token) {
      setAuthSession(response.data.token, response.data.user);
      setUser(response.data.user);
    }

    return response;
  }, []);

  const verifyEmail = useCallback(async (payload) => {
    const response = await verifyEmailRequest(payload);
    setAuthSession(response.data.token, response.data.user);
    setUser(response.data.user);
    return response;
  }, []);

  const resendVerification = useCallback(async (payload) => {
    return resendVerificationRequest(payload);
  }, []);

  const logout = useCallback(async () => {
    await logoutRequest();
    setUser(null);
  }, []);

  const updateUser = useCallback((nextUser) => {
    setUser(nextUser);
    localStorage.setItem("auth_user", JSON.stringify(nextUser));
  }, []);

  const canAccess = useCallback(
    (module) => canAccessModule(user?.role?.slug, module),
    [user],
  );

  const value = useMemo(
    () => ({
      user,
      loading,
      isAuthenticated: Boolean(user && getStoredToken()),
      login,
      register,
      verifyEmail,
      resendVerification,
      logout,
      canAccess,
    }),
    [user, loading, login, register, verifyEmail, resendVerification, logout, canAccess],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }

  return context;
}
