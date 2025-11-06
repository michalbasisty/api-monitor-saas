package main

import (
"log"
"net/http"
"os"

	"api-monitor-go/internal/database"
"api-monitor-go/internal/monitoring"
"api-monitor-go/internal/websocket"
"github.com/joho/godotenv"
"github.com/redis/go-redis/v9"
gorillaWs "github.com/gorilla/websocket"
)

var upgrader = gorillaWs.Upgrader{
	CheckOrigin: func(r *http.Request) bool {
		return true // Allow all origins for dev
	},
}

func main() {
	// Load environment variables
	if err := godotenv.Load("../../.env"); err != nil {
		log.Println("No .env file found, using environment variables")
	}

	// Initialize database
	db, err := database.NewDB()
	if err != nil {
	log.Fatal("Failed to initialize database:", err)
	}
	defer db.Close()

	// Initialize Redis client
	redisHost := os.Getenv("REDIS_HOST")
	if redisHost == "" {
	redisHost = "redis"
	}
	redisPortStr := os.Getenv("REDIS_PORT")
	if redisPortStr == "" {
	redisPortStr = "6379"
	}
	rdb := redis.NewClient(&redis.Options{
	 Addr: redisHost + ":" + redisPortStr,
	DB:   0,
	})
	defer rdb.Close()

	// Initialize repository
	repo := database.NewRepository(db.Postgres)

	// Initialize WebSocket hub
	hub := websocket.NewHub()
	go hub.Run()

	// Initialize monitoring service
	monitorService := monitoring.NewService(repo, hub, rdb)

	// WebSocket endpoint
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Printf("WebSocket upgrade failed: %v", err)
			return
		}
		hub.Register(conn)
	})

	// Monitoring endpoint
	http.HandleFunc("/monitor", func(w http.ResponseWriter, r *http.Request) {
		go func() {
			if err := monitorService.MonitorEndpoints(); err != nil {
				log.Printf("Monitoring failed: %v", err)
			}
		}()
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("Monitoring started"))
	})

	// Health check
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})

	port := os.Getenv("GO_API_PORT")
	if port == "" {
		port = "8080"
	}

	log.Printf("Go API server starting on port %s", port)
	log.Fatal(http.ListenAndServe(":"+port, nil))
}
