import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  MonitoringResult,
  MonitoringStats,
  MonitoringTimeline
} from '../models/monitoring.model';

@Injectable({
  providedIn: 'root'
})
export class MonitoringService {
  private readonly API_URL = `${environment.apiUrl}/monitoring`;

  constructor(private http: HttpClient) {}

  getResults(endpointId: string, limit: number = 100): Observable<{
    endpoint_id: string;
    total: number;
    results: MonitoringResult[];
  }> {
    return this.http.get<any>(`${this.API_URL}/endpoints/${endpointId}/results?limit=${limit}`);
  }

  getStats(endpointId: string, hours: number = 24): Observable<MonitoringStats> {
    return this.http.get<MonitoringStats>(`${this.API_URL}/endpoints/${endpointId}/stats?hours=${hours}`);
  }

  getTimeline(endpointId: string, hours: number = 24): Observable<MonitoringTimeline> {
    return this.http.get<MonitoringTimeline>(`${this.API_URL}/endpoints/${endpointId}/timeline?hours=${hours}`);
  }
}
