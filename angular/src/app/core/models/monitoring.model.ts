export interface MonitoringResult {
  id: string;
  response_time: number | null;
  status_code: number | null;
  error_message: string | null;
  is_successful: boolean;
  checked_at: string;
  created_at: string;
}

export interface MonitoringStats {
  endpoint_id: string;
  period_hours: number;
  latest_check: {
    status_code: number | null;
    response_time: number | null;
    is_successful: boolean;
    error_message: string | null;
    checked_at: string;
  } | null;
  average_response_time: number | null;
  uptime_percentage: number;
}

export interface MonitoringTimeline {
  endpoint_id: string;
  from: string;
  to: string;
  total: number;
  timeline: TimelinePoint[];
}

export interface TimelinePoint {
  checked_at: string;
  status_code: number | null;
  response_time: number | null;
  is_successful: boolean;
}
