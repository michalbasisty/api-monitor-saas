package websocket

import (
	"sync"
	"time"

	"api-monitor-go/internal/logger"
	"api-monitor-go/internal/models"
	"github.com/gorilla/websocket"
)

// Hub manages WebSocket clients and broadcasts monitoring results to subscribers.
type Hub struct {
	clients    map[*websocket.Conn]bool
	broadcast  chan models.MonitoringResult
	register   chan *websocket.Conn
	unregister chan *websocket.Conn
	mu         sync.Mutex
	log        *logger.Logger
	done       chan struct{}
}

func NewHub() *Hub {
	return &Hub{
		clients:    make(map[*websocket.Conn]bool),
		broadcast:  make(chan models.MonitoringResult, 100), // Buffered channel
		register:   make(chan *websocket.Conn),
		unregister: make(chan *websocket.Conn),
		log:        logger.New(),
		done:       make(chan struct{}),
	}
}

func (h *Hub) Run() {
	h.log.Info("WebSocket hub started")

	// Start heartbeat ticker for ping/pong
	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case client := <-h.register:
			h.registerClient(client)

		case client := <-h.unregister:
			h.unregisterClient(client)

		case result := <-h.broadcast:
			h.broadcastToClients(result)

		case <-ticker.C:
			h.sendPings()

		case <-h.done:
			h.shutdown()
			return
		}
	}
}

// registerClient registers a new WebSocket client
func (h *Hub) registerClient(client *websocket.Conn) {
	h.mu.Lock()
	defer h.mu.Unlock()

	h.clients[client] = true
	clientCount := len(h.clients)
	h.log.WithFields(map[string]interface{}{
		"connected_clients": clientCount,
		"remote_addr":       client.RemoteAddr(),
	}).Info("WebSocket client connected")
}

// unregisterClient unregisters a WebSocket client
func (h *Hub) unregisterClient(client *websocket.Conn) {
	h.mu.Lock()
	defer h.mu.Unlock()

	if _, ok := h.clients[client]; ok {
		delete(h.clients, client)
		client.Close()
		clientCount := len(h.clients)
		h.log.WithFields(map[string]interface{}{
			"connected_clients": clientCount,
			"remote_addr":       client.RemoteAddr(),
		}).Info("WebSocket client disconnected")
	}
}

// Broadcast queues a monitoring result to be sent to all connected clients.
func (h *Hub) Broadcast(result models.MonitoringResult) {
	select {
	case h.broadcast <- result:
	default:
		h.log.Warn("broadcast channel full, skipping message")
	}
}

// broadcastToClients sends the result to all connected clients asynchronously
func (h *Hub) broadcastToClients(result models.MonitoringResult) {
	h.mu.Lock()
	clients := make([]*websocket.Conn, 0, len(h.clients))
	for client := range h.clients {
		clients = append(clients, client)
	}
	h.mu.Unlock()

	failedClients := make([]*websocket.Conn, 0)

	for _, client := range clients {
		// Send in goroutine to avoid blocking on slow clients
		go func(c *websocket.Conn) {
			if err := c.WriteJSON(result); err != nil {
				h.log.WithField("error", err.Error()).Debug("failed to write to client")
				failedClients = append(failedClients, c)
			}
		}(client)
	}

	// Unregister failed clients
	for _, client := range failedClients {
		h.Unregister(client)
	}

	h.log.WithFields(map[string]interface{}{
		"endpoint_id":   result.EndpointID,
		"response_time": result.ResponseTime,
		"clients_count": len(clients),
	}).Debug("broadcast completed")
}

// sendPings sends ping messages to all connected clients
func (h *Hub) sendPings() {
	h.mu.Lock()
	clients := make([]*websocket.Conn, 0, len(h.clients))
	for client := range h.clients {
		clients = append(clients, client)
	}
	h.mu.Unlock()

	for _, client := range clients {
		if err := client.WriteMessage(websocket.PingMessage, nil); err != nil {
			h.log.WithField("error", err.Error()).Debug("failed to send ping")
			h.Unregister(client)
		}
	}
}

// Register enqueues a client connection to be tracked by the hub.
func (h *Hub) Register(client *websocket.Conn) {
	h.register <- client
}

// Unregister enqueues a client connection to be removed from the hub.
func (h *Hub) Unregister(client *websocket.Conn) {
	h.unregister <- client
}

// GetClientCount returns the number of connected clients
func (h *Hub) GetClientCount() int {
	h.mu.Lock()
	defer h.mu.Unlock()
	return len(h.clients)
}

// Stop stops the hub gracefully
func (h *Hub) Stop() {
	close(h.done)
}

// shutdown closes all client connections
func (h *Hub) shutdown() {
	h.mu.Lock()
	defer h.mu.Unlock()

	for client := range h.clients {
		client.Close()
	}
	h.clients = make(map[*websocket.Conn]bool)
	h.log.Info("WebSocket hub shutdown")
}
