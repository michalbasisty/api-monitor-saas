import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { AlertService } from './alert.service';
import { environment } from '../../../environments/environment';
import { Alert, CreateAlertRequest } from '../models/alert.model';

describe('AlertService', () => {
  let service: AlertService;
  let httpMock: HttpTestingController;
  const apiUrl = `${environment.apiUrl}/alerts`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [AlertService]
    });
    service = TestBed.inject(AlertService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  // ============================================
  // getAlerts Tests
  // ============================================

  it('should fetch all alerts', (done) => {
    const mockAlerts: Alert[] = [
      {
        id: 'alert-1',
        endpoint_id: 'endpoint-1',
        type: 'downtime',
        condition: 'status_code == 500',
        is_active: true,
        triggered_count: 2,
        created_at: '2024-11-11T10:00:00Z'
      },
      {
        id: 'alert-2',
        endpoint_id: 'endpoint-2',
        type: 'slow_response',
        condition: 'response_time > 5000',
        is_active: true,
        triggered_count: 0,
        created_at: '2024-11-11T11:00:00Z'
      }
    ];

    service.getAlerts().subscribe(data => {
      expect(data.total).toBe(2);
      expect(data.alerts.length).toBe(2);
      expect(data.alerts[0].type).toBe('downtime');
      done();
    });

    const req = httpMock.expectOne(apiUrl);
    expect(req.request.method).toBe('GET');
    req.flush({ total: 2, alerts: mockAlerts });
  });

  it('should handle empty alerts list', (done) => {
    service.getAlerts().subscribe(data => {
      expect(data.total).toBe(0);
      expect(data.alerts.length).toBe(0);
      done();
    });

    const req = httpMock.expectOne(apiUrl);
    req.flush({ total: 0, alerts: [] });
  });

  it('should include alert metadata', (done) => {
    const mockAlert: Alert = {
      id: 'alert-1',
      endpoint_id: 'endpoint-1',
      type: 'downtime',
      condition: 'status_code >= 400',
      is_active: true,
      triggered_count: 5,
      created_at: '2024-11-11T10:00:00Z'
    };

    service.getAlerts().subscribe(data => {
      const alert = data.alerts[0];
      expect(alert.id).toBe('alert-1');
      expect(alert.triggered_count).toBe(5);
      expect(alert.is_active).toBe(true);
      done();
    });

    const req = httpMock.expectOne(apiUrl);
    req.flush({ total: 1, alerts: [mockAlert] });
  });

  // ============================================
  // getAlert Tests
  // ============================================

  it('should fetch single alert by id', (done) => {
    const alertId = 'alert-123';
    const mockAlert: Alert = {
      id: alertId,
      endpoint_id: 'endpoint-1',
      type: 'downtime',
      condition: 'status_code == 500',
      is_active: true,
      triggered_count: 3,
      created_at: '2024-11-11T10:00:00Z'
    };

    service.getAlert(alertId).subscribe(data => {
      expect(data.id).toBe(alertId);
      expect(data.type).toBe('downtime');
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    expect(req.request.method).toBe('GET');
    req.flush(mockAlert);
  });

  it('should handle non-existent alert', (done) => {
    const alertId = 'non-existent';

    service.getAlert(alertId).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(404);
        done();
      }
    );

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    req.flush('Not found', { status: 404, statusText: 'Not Found' });
  });

  // ============================================
  // getAlertsByEndpoint Tests
  // ============================================

  it('should fetch alerts for specific endpoint', (done) => {
    const endpointId = 'endpoint-456';
    const mockAlerts: Alert[] = [
      {
        id: 'alert-1',
        endpoint_id: endpointId,
        type: 'downtime',
        condition: 'status_code >= 400',
        is_active: true,
        triggered_count: 1,
        created_at: '2024-11-11T10:00:00Z'
      }
    ];

    service.getAlertsByEndpoint(endpointId).subscribe(data => {
      expect(data.endpoint_id).toBe(endpointId);
      expect(data.alerts.length).toBe(1);
      expect(data.alerts[0].endpoint_id).toBe(endpointId);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoint/${endpointId}`);
    expect(req.request.method).toBe('GET');
    req.flush({ endpoint_id: endpointId, total: 1, alerts: mockAlerts });
  });

  it('should return empty list if endpoint has no alerts', (done) => {
    const endpointId = 'endpoint-no-alerts';

    service.getAlertsByEndpoint(endpointId).subscribe(data => {
      expect(data.endpoint_id).toBe(endpointId);
      expect(data.total).toBe(0);
      expect(data.alerts.length).toBe(0);
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/endpoint/${endpointId}`);
    req.flush({ endpoint_id: endpointId, total: 0, alerts: [] });
  });

  // ============================================
  // createAlert Tests
  // ============================================

  it('should create new alert', (done) => {
    const alertRequest: CreateAlertRequest = {
      endpoint_id: 'endpoint-1',
      type: 'downtime',
      condition: 'status_code >= 400'
    };

    const mockAlert: Alert = {
      id: 'alert-new',
      endpoint_id: alertRequest.endpoint_id,
      type: alertRequest.type,
      condition: alertRequest.condition,
      is_active: true,
      triggered_count: 0,
      created_at: '2024-11-11T12:00:00Z'
    };

    service.createAlert(alertRequest).subscribe(data => {
      expect(data.message).toContain('created');
      expect(data.alert.id).toBe('alert-new');
      expect(data.alert.is_active).toBe(true);
      done();
    });

    const req = httpMock.expectOne(apiUrl);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(alertRequest);
    req.flush({ message: 'Alert created', alert: mockAlert });
  });

  it('should send correct alert data', (done) => {
    const alertRequest: CreateAlertRequest = {
      endpoint_id: 'endpoint-1',
      type: 'slow_response',
      condition: 'response_time > 5000'
    };

    service.createAlert(alertRequest).subscribe(() => {
      done();
    });

    const req = httpMock.expectOne(apiUrl);
    expect(req.request.body.endpoint_id).toBe('endpoint-1');
    expect(req.request.body.type).toBe('slow_response');
    expect(req.request.body.condition).toBe('response_time > 5000');
    req.flush({ message: 'Alert created', alert: {} as Alert });
  });

  it('should handle creation error', (done) => {
    const alertRequest: CreateAlertRequest = {
      endpoint_id: 'endpoint-1',
      type: 'downtime',
      condition: 'status_code == 500'
    };

    service.createAlert(alertRequest).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(400);
        done();
      }
    );

    const req = httpMock.expectOne(apiUrl);
    req.flush('Invalid alert', { status: 400, statusText: 'Bad Request' });
  });

  // ============================================
  // updateAlert Tests
  // ============================================

  it('should update existing alert', (done) => {
    const alertId = 'alert-123';
    const updateData: Partial<CreateAlertRequest> = {
      condition: 'response_time > 3000'
    };

    const mockAlert: Alert = {
      id: alertId,
      endpoint_id: 'endpoint-1',
      type: 'slow_response',
      condition: updateData.condition!,
      is_active: true,
      triggered_count: 0,
      created_at: '2024-11-11T10:00:00Z'
    };

    service.updateAlert(alertId, updateData).subscribe(data => {
      expect(data.message).toContain('updated');
      expect(data.alert.condition).toBe('response_time > 3000');
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual(updateData);
    req.flush({ message: 'Alert updated', alert: mockAlert });
  });

  it('should handle partial updates', (done) => {
    const alertId = 'alert-456';
    const updateData: Partial<CreateAlertRequest> = {
      type: 'downtime'
    };

    service.updateAlert(alertId, updateData).subscribe(() => {
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    expect(req.request.body).toEqual(updateData);
    req.flush({ message: 'Updated', alert: {} as Alert });
  });

  // ============================================
  // deleteAlert Tests
  // ============================================

  it('should delete alert', (done) => {
    const alertId = 'alert-delete';

    service.deleteAlert(alertId).subscribe(data => {
      expect(data.message).toContain('deleted');
      done();
    });

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    expect(req.request.method).toBe('DELETE');
    req.flush({ message: 'Alert deleted' });
  });

  it('should handle delete errors', (done) => {
    const alertId = 'alert-error';

    service.deleteAlert(alertId).subscribe(
      () => fail('should not succeed'),
      (error) => {
        expect(error.status).toBe(403);
        done();
      }
    );

    const req = httpMock.expectOne(`${apiUrl}/${alertId}`);
    req.flush('Forbidden', { status: 403, statusText: 'Forbidden' });
  });

  // ============================================
  // Multiple Operation Tests
  // ============================================

  it('should handle multiple concurrent requests', (done) => {
    const alertIds = ['alert-1', 'alert-2', 'alert-3'];
    let completed = 0;

    alertIds.forEach(id => {
      service.getAlert(id).subscribe(() => {
        completed++;
        if (completed === alertIds.length) {
          done();
        }
      });
    });

    const reqs = httpMock.match(req => req.url.includes(`${apiUrl}/alert-`));
    expect(reqs.length).toBe(3);
    reqs.forEach(req => {
      req.flush({ id: 'alert' } as Alert);
    });
  });
});
