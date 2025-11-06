export interface Endpoint {
  id: string;
  url: string;
  check_interval: number;
  timeout: number;
  headers: Record<string, string> | null;
  is_active: boolean;
  created_at: string;
  updated_at: string | null;
}

export interface CreateEndpointRequest {
  url: string;
  check_interval: number;
  timeout: number;
  headers?: Record<string, string>;
  is_active?: boolean;
}

export interface EndpointListResponse {
  total: number;
  endpoints: Endpoint[];
}
