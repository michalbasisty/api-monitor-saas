import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { 
  Endpoint, 
  CreateEndpointRequest, 
  EndpointListResponse 
} from '../models/endpoint.model';

@Injectable({
  providedIn: 'root'
})
export class EndpointService {
  private readonly API_URL = `${environment.apiUrl}/endpoints`;

  constructor(private http: HttpClient) {}

  getEndpoints(): Observable<EndpointListResponse> {
    return this.http.get<EndpointListResponse>(this.API_URL);
  }

  getEndpoint(id: string): Observable<Endpoint> {
    return this.http.get<Endpoint>(`${this.API_URL}/${id}`);
  }

  createEndpoint(data: CreateEndpointRequest): Observable<{message: string; endpoint: Endpoint}> {
    return this.http.post<{message: string; endpoint: Endpoint}>(this.API_URL, data);
  }

  updateEndpoint(id: string, data: Partial<CreateEndpointRequest>): Observable<{message: string; endpoint: Endpoint}> {
    return this.http.put<{message: string; endpoint: Endpoint}>(`${this.API_URL}/${id}`, data);
  }

  deleteEndpoint(id: string): Observable<{message: string}> {
    return this.http.delete<{message: string}>(`${this.API_URL}/${id}`);
  }
}
