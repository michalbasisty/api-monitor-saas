import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Alert, CreateAlertRequest } from '../models/alert.model';

@Injectable({
  providedIn: 'root'
})
export class AlertService {
  private readonly API_URL = `${environment.apiUrl}/alerts`;

  constructor(private http: HttpClient) {}

  getAlerts(): Observable<{total: number; alerts: Alert[]}> {
    return this.http.get<{total: number; alerts: Alert[]}>(this.API_URL);
  }

  getAlert(id: string): Observable<Alert> {
    return this.http.get<Alert>(`${this.API_URL}/${id}`);
  }

  getAlertsByEndpoint(endpointId: string): Observable<{endpoint_id: string; total: number; alerts: Alert[]}> {
    return this.http.get<any>(`${this.API_URL}/endpoint/${endpointId}`);
  }

  createAlert(data: CreateAlertRequest): Observable<{message: string; alert: Alert}> {
    return this.http.post<{message: string; alert: Alert}>(this.API_URL, data);
  }

  updateAlert(id: string, data: Partial<CreateAlertRequest>): Observable<{message: string; alert: Alert}> {
    return this.http.put<{message: string; alert: Alert}>(`${this.API_URL}/${id}`, data);
  }

  deleteAlert(id: string): Observable<{message: string}> {
    return this.http.delete<{message: string}>(`${this.API_URL}/${id}`);
  }
}
