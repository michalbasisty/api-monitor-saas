package websocket

import (
	"log"
	"sync"

	"api-monitor-go/internal/models"
	"github.com/gorilla/websocket"
)

type Hub struct {
	clients    map[*websocket.Conn]bool
	broadcast  chan models.MonitoringResult
	register   chan *websocket.Conn
	unregister chan *websocket.Conn
	mu         sync.Mutex
}

func NewHub() *Hub {
	return &Hub{
		clients:    make(map[*websocket.Conn]bool),
		broadcast:  make(chan models.MonitoringResult, 100), // Buffered channel
		register:   make(chan *websocket.Conn),
		unregister: make(chan *websocket.Conn),
	}
}

func (h *Hub) Run() {
	for {
		select {
		case client := <-h.register:
			h.mu.Lock()
			h.clients[client] = true
			h.mu.Unlock()
			log.Println("WebSocket client connected")

		case client := <-h.unregister:
			h.mu.Lock()
			if _, ok := h.clients[client]; ok {
				delete(h.clients, client)
				client.Close()
			}
			h.mu.Unlock()
			log.Println("WebSocket client disconnected")

		case result := <-h.broadcast:
			h.broadcastToClients(result)
		}
	}
}

func (h *Hub) Broadcast(result models.MonitoringResult) {
	select {
	case h.broadcast <- result:
	default:
		log.Println("Broadcast channel full, skipping")
	}
}

func (h *Hub) broadcastToClients(result models.MonitoringResult) {
	h.mu.Lock()
	defer h.mu.Unlock()
	for client := range h.clients {
		if err := client.WriteJSON(result); err != nil {
			client.Close()
			delete(h.clients, client)
		}
	}
}

func (h *Hub) Register(client *websocket.Conn) {
	h.register <- client
}

func (h *Hub) Unregister(client *websocket.Conn) {
	h.unregister <- client
}
