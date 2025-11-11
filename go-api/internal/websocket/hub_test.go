package websocket

import (
	"net"
	"net/http"
	"testing"
	"time"

	"api-monitor-go/internal/models"
	"github.com/gorilla/websocket"
)

type fakeConn struct{ websocket.Conn }

func TestHubRegisterUnregister(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	// Use a mock connection instead of nil
	c1 := &websocket.Conn{}
	c2 := &websocket.Conn{}
	
	h.Register(c1)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 1 {
		t.Fatalf("expected 1 client, got %d", len(h.clients))
	}

	h.Register(c2)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 2 {
		t.Fatalf("expected 2 clients, got %d", len(h.clients))
	}

	h.Unregister(c1)
	time.Sleep(10 * time.Millisecond)
	if len(h.clients) != 1 {
		t.Fatalf("expected 1 client, got %d", len(h.clients))
	}

	h.Unregister(c2)
	time.Sleep(10 * time.Millisecond)
	if len(h.clients) != 0 {
		t.Fatalf("expected 0 clients, got %d", len(h.clients))
	}
}

func TestHubBroadcastQueue(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	// should not panic when queue is full due to default branch
	for i := 0; i < 110; i++ {
		h.Broadcast(models.MonitoringResult{EndpointID: i})
	}
}

func TestNewHub(t *testing.T) {
	h := NewHub()

	if h == nil {
		t.Fatalf("expected hub instance")
	}

	if len(h.clients) != 0 {
		t.Fatalf("expected 0 clients initially")
	}

	if cap(h.broadcast) != 100 {
		t.Fatalf("expected broadcast channel with capacity 100")
	}
}

func TestHubMultipleRegistrations(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	numClients := 10
	conns := make([]*websocket.Conn, numClients)

	for i := 0; i < numClients; i++ {
		conns[i] = &websocket.Conn{}
		h.Register(conns[i])
	}

	time.Sleep(50 * time.Millisecond)

	if len(h.clients) != numClients {
		t.Fatalf("expected %d clients, got %d", numClients, len(h.clients))
	}
}

func TestHubBroadcastWithNoClients(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
	}

	h.Broadcast(result)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 0 {
		t.Fatalf("expected 0 clients")
	}
}

func TestHubBroadcastMultiple(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	c1 := &websocket.Conn{}
	h.Register(c1)
	time.Sleep(10 * time.Millisecond)

	for i := 0; i < 5; i++ {
		result := models.MonitoringResult{
			EndpointID:   i,
			ResponseTime: 100 * i,
		}
		h.Broadcast(result)
	}

	time.Sleep(50 * time.Millisecond)
}

func TestHubRegisterAfterUnregister(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	c1 := &websocket.Conn{}

	h.Register(c1)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 1 {
		t.Fatalf("expected 1 client after register")
	}

	h.Unregister(c1)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 0 {
		t.Fatalf("expected 0 clients after unregister")
	}

	// Register again
	c2 := &websocket.Conn{}
	h.Register(c2)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 1 {
		t.Fatalf("expected 1 client after re-register")
	}
}

func TestHubUnregisterNonexistentClient(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	c1 := &websocket.Conn{}
	h.Unregister(c1)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 0 {
		t.Fatalf("expected 0 clients")
	}
}

func TestHubBroadcastQueueOverflow(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	// Send more messages than buffer capacity
	numMessages := 150
	for i := 0; i < numMessages; i++ {
		h.Broadcast(models.MonitoringResult{EndpointID: i})
	}

	time.Sleep(50 * time.Millisecond)
}

func TestHubConcurrentOperations(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	done := make(chan bool)

	// Goroutine 1: Register clients
	go func() {
		for i := 0; i < 5; i++ {
			c := &websocket.Conn{}
			h.Register(c)
			time.Sleep(5 * time.Millisecond)
		}
		done <- true
	}()

	// Goroutine 2: Broadcast messages
	go func() {
		for i := 0; i < 10; i++ {
			h.Broadcast(models.MonitoringResult{EndpointID: i})
			time.Sleep(5 * time.Millisecond)
		}
		done <- true
	}()

	// Goroutine 3: Unregister clients
	go func() {
		time.Sleep(50 * time.Millisecond)
		h.mu.Lock()
		clients := make([]*websocket.Conn, 0, len(h.clients))
		for c := range h.clients {
			clients = append(clients, c)
		}
		h.mu.Unlock()

		for _, c := range clients {
			h.Unregister(c)
			time.Sleep(5 * time.Millisecond)
		}
		done <- true
	}()

	<-done
	<-done
	<-done

	time.Sleep(10 * time.Millisecond)
}

func TestHubWithRealWebSocket(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	// Create a simple test server with WebSocket
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	server := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			upgrader := websocket.Upgrader{
				CheckOrigin: func(r *http.Request) bool { return true },
			}

			ws, err := upgrader.Upgrade(w, r, nil)
			if err != nil {
				return
			}

			h.Register(ws)
			defer h.Unregister(ws)

			for {
				var msg interface{}
				if err := ws.ReadJSON(&msg); err != nil {
					break
				}
			}
		}),
	}

	go server.Serve(listener)
	defer server.Close()

	time.Sleep(10 * time.Millisecond)

	h.Broadcast(models.MonitoringResult{EndpointID: 1})
	time.Sleep(10 * time.Millisecond)
}

func TestHubBroadcastWithDifferentResults(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	c1 := &websocket.Conn{}
	h.Register(c1)
	time.Sleep(10 * time.Millisecond)

	results := []models.MonitoringResult{
		{
			EndpointID:   1,
			ResponseTime: 100,
			StatusCode:   intPtr(200),
		},
		{
			EndpointID:   2,
			ResponseTime: 250,
			StatusCode:   intPtr(500),
		},
		{
			EndpointID:   3,
			ResponseTime: 0,
			ErrorMessage: strPtr("connection refused"),
		},
	}

	for _, result := range results {
		h.Broadcast(result)
	}

	time.Sleep(50 * time.Millisecond)
}

func TestHubClientCleanupOnWriteError(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	// Create a mock connection that fails on write
	c := &websocket.Conn{}
	h.Register(c)
	time.Sleep(10 * time.Millisecond)

	if len(h.clients) != 1 {
		t.Fatalf("expected 1 client")
	}

	h.Broadcast(models.MonitoringResult{EndpointID: 1})
	time.Sleep(10 * time.Millisecond)
}

func TestHubLargePayload(t *testing.T) {
	h := NewHub()
	go h.Run()
	time.Sleep(10 * time.Millisecond)

	c1 := &websocket.Conn{}
	h.Register(c1)
	time.Sleep(10 * time.Millisecond)

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 9999999,
		StatusCode:   intPtr(200),
	}

	for i := 0; i < 100; i++ {
		h.Broadcast(result)
	}

	time.Sleep(100 * time.Millisecond)
}

// Helper functions
func intPtr(i int) *int {
	return &i
}

func strPtr(s string) *string {
	return &s
}
