import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { EndpointService } from './endpoint.service';

interface Endpoint {
  id: string;
  url: string;
  name: string;
  is_active: boolean;
  created_at: string;
}

describe('EndpointService', () => {
  let service: EndpointService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [EndpointService]
    });
    service = TestBed.inject(EndpointService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should fetch all endpoints', () => {
    const mock: Endpoint[] = [
      { id: '1', url: 'http://a', name: 'A', is_active: true, created_at: '' }
    ];

    service.getEndpoints().subscribe((resp: any) => {
      expect(resp.endpoints.length).toBe(1);
      expect(resp.endpoints[0].id).toBe('1');
    });

    const req = httpMock.expectOne('/api/endpoints');
    expect(req.request.method).toBe('GET');
    req.flush({ endpoints: mock });
  });

  it('should fetch single endpoint by id', () => {
    const endpoint: Endpoint = { id: '2', url: 'http://b', name: 'B', is_active: false, created_at: '' };

    service.getEndpoint('2').subscribe(resp => {
      expect(resp.id).toBe('2');
      expect(resp.url).toBe('http://b');
    });

    const req = httpMock.expectOne('/api/endpoints/2');
    expect(req.request.method).toBe('GET');
    req.flush(endpoint);
  });

  it('should create an endpoint', () => {
    const payload = { url: 'http://c', name: 'C', is_active: true } as any;
    const created = { id: '3', ...payload, created_at: '' } as Endpoint;

    service.createEndpoint(payload).subscribe(resp => {
      expect(resp.id).toBe('3');
      expect(resp.url).toBe('http://c');
    });

    const req = httpMock.expectOne('/api/endpoints');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(payload);
    req.flush(created);
  });

  it('should update an endpoint', () => {
    const update = { name: 'C2', is_active: false } as any;
    const id = '3';

    service.updateEndpoint(id, update).subscribe(resp => {
      expect(resp.name).toBe('C2');
      expect(resp.is_active).toBe(false);
    });

    const req = httpMock.expectOne(`/api/endpoints/${id}`);
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual(update);
    req.flush({ id, url: 'http://c', name: 'C2', is_active: false, created_at: '' });
  });

  it('should delete an endpoint', () => {
    const id = '4';
    service.deleteEndpoint(id).subscribe(resp => {
      expect(resp).toEqual({ message: 'deleted' });
    });

    const req = httpMock.expectOne(`/api/endpoints/${id}`);
    expect(req.request.method).toBe('DELETE');
    req.flush({ message: 'deleted' });
  });
});
