export interface Alert {
  id: string;
  endpoint_id: string;
  alert_type: 'response_time' | 'status_code' | 'availability';
  threshold: AlertThreshold;
  is_active: boolean;
  notification_channels: string[];
  last_triggered_at: string | null;
  created_at: string;
  updated_at: string | null;
}

export type AlertThreshold =
  | ResponseTimeThreshold
  | StatusCodeThreshold
  | AvailabilityThreshold;

export interface ResponseTimeThreshold {
  max_response_time: number;
}

export interface StatusCodeThreshold {
  expected_codes?: number[];
  min_code?: number;
  max_code?: number;
  alert_on_null?: boolean;
}

export interface AvailabilityThreshold {
  min_uptime_percentage: number;
  period_hours: number;
}

export interface CreateAlertRequest {
  endpoint_id: string;
  alert_type: string;
  threshold: AlertThreshold;
  is_active?: boolean;
  notification_channels?: string[];
}
