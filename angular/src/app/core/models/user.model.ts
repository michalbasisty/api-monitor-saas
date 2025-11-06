export interface User {
  id: string;
  email: string;
  roles: string[];
  subscription_tier: string;
  is_verified: boolean;
  company_id: string | null;
  created_at: string;
  last_login_at: string | null;
}

export interface LoginRequest {
  username: string;
  password: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  company_id?: string;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface RegisterResponse {
  message: string;
  id: string;
  verification_token: string;
}
