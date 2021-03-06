package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"math/rand"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/dgrijalva/jwt-go"
	_ "github.com/go-sql-driver/mysql"
	"github.com/gorilla/context"
	"github.com/gorilla/mux"

	"jface/questlog/API"
)

const (
	SERVICE_PATH              = "/service"
	QUESTLOG_SESSION_ID       = "questlog-user"
	PORT                      = ":1337"
	DEFAULT_QUEST_PAGE_LENGTH = 50
)

type LoginModel struct {
	Uid   int    `json:"uid"`
	Name  string `json:"name"`
	Stamp int    `json:"last_login_time"`
	Ip    string `json:"ip"`
}

func getIPAddress(r *http.Request) string {
	var ipAddress string
	for _, h := range []string{"X-Forwarded-For", "X-Real-Ip"} {
		for _, ip := range strings.Split(r.Header.Get(h), ",") {
			// header can contain spaces too, strip those out.
			//realIP := net.ParseIP(strings.Replace(ip, " ", "", -1))
			ipAddress = ip
		}
	}
	return ipAddress
}

var devMachine string

func getConfigurationAndSetDBCredentials() {
	file, _ := os.Open("conf.json")
	defer file.Close()
	decoder := json.NewDecoder(file)
	var db_creds API.Creds
	err := decoder.Decode(&db_creds)
	if err != nil {
		fmt.Println("error:", err)
	}
	API.SetCredentials(db_creds)
	devMachine = db_creds.PcName
}

func validationMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		(w).Header().Set("Access-Control-Allow-Origin", "*")
		(w).Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS, PUT, DELETE")
		(w).Header().Set("Access-Control-Allow-Headers", "src, Accept, Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization")
		if (*r).Method == "OPTIONS" {
			return
		}
		authorizationHeader := r.Header.Get("Authorization")
		if authorizationHeader != "" {
			bearerToken := strings.Split(authorizationHeader, " ")
			if len(bearerToken) == 2 {
				token, err := jwt.Parse(bearerToken[1], func(token *jwt.Token) (interface{}, error) {
					if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
						API.RespondWithError(w, http.StatusBadRequest, "Error parsing token")
					}
					return []byte(API.GetSecret()), nil
				})
				switch err.(type) {
				case nil:
					if token.Valid {
						next(w, r)
					} else {
						API.RespondWithError(w, http.StatusUnauthorized, "Invalid authorization token")
						return
					}
				case *jwt.ValidationError: // something was wrong during the validation
					vErr := err.(*jwt.ValidationError)
					switch vErr.Errors {
					case jwt.ValidationErrorExpired:
						API.RespondWithError(w, http.StatusUnauthorized, "Token expired")
						return
					default:
						API.RespondWithError(w, http.StatusBadRequest, "Error parsing token")
						return
					}
				}
			}
		} else {
			API.RespondWithError(w, http.StatusBadRequest, "An authorization header is required")
			return
		}
	})
}

func handleViewQuest(w http.ResponseWriter, r *http.Request) {
	proto := "http://"
	fmt.Println("wtf", r.URL.Scheme)
	if len(r.URL.Scheme) > 0 {
		proto = "https://"
	}
	json, code, err := apiRequest(w, r, proto+r.Host+SERVICE_PATH+"/quest/"+mux.Vars(r)["qid"]+"?start=0&length=1&order=ASC", "GET")
	if err != nil {
		http.Error(w, "Internal Server Error", code)
		return
	}
	fmt.Println(json)
	/*
	  if count > 0 {
	    http.ServeFile(w, r, "./static/")
	  } else {
	    http.Error(w, "No such quest", http.StatusNotFound)
	  }*/
}

func apiRequest(w http.ResponseWriter, r *http.Request, url string, typeReq string) (map[string]interface{}, int, error) {
	fmt.Println("url", url)
	req, err := http.NewRequest(typeReq, url, nil)
	req.Header.Add("content-type", "application/json")
	req.Header.Add("cache-control", "no-cache")
	if err != nil {
		fmt.Println(err)
	}
	res, err := http.DefaultClient.Do(req)
	if err != nil {
		fmt.Println(err)
	}

	defer res.Body.Close()
	body, err := ioutil.ReadAll(res.Body)
	if err != nil {
		fmt.Println(err)
	}
	jsonmap := make(map[string]interface{})
	err = json.Unmarshal(body, &jsonmap)
	if err != nil {
		fmt.Println("error:", err)
	}
	if res.StatusCode != http.StatusOK {
		return nil, res.StatusCode, errors.New(fmt.Sprintf("%v", jsonmap["error"]))
	}
	return jsonmap, http.StatusOK, nil
}

func serveStatic(w http.ResponseWriter, r *http.Request) {
	http.ServeFile(w, r, "./static/")
}

func main() {

	rand.Seed(time.Now().Unix())

	getConfigurationAndSetDBCredentials()
	rtr := mux.NewRouter()

	//GETs
	//rtr.HandleFunc(SERVICE_PATH+"/quests", handleQuests).Methods("GET")
	//rtr.HandleFunc(SERVICE_PATH + "/quests", validationMiddleware(API.FetchQuests)).Methods("GET", "OPTIONS")
	rtr.HandleFunc(SERVICE_PATH+"/quests", API.FetchQuests).Methods("GET", "OPTIONS")
	rtr.HandleFunc(SERVICE_PATH+"/quest/{qid:[0-9]+}", API.FetchQuest).Methods("GET", "OPTIONS")

	/*
		rtr.HandleFunc(SERVICE_PATH+"/quest/{[0-9]+}", handleQuest).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/quest/{[0-9]+}/info", handleQuestInfo).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/quest/{[0-9]+}/permissions", handleQuestPermissions).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/quest/{[0-9]+}/delete", handleQuestDelete).Methods("DELETE")
		rtr.HandleFunc(SERVICE_PATH+"/login", handleLogin).Methods("POST")
		rtr.HandleFunc(SERVICE_PATH+"/logout", handleLogout).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/checkSession", handleSessionCheck).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/character/{[0-9]+}", handleCharacterInfo).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/quest/{[0-9]+}/post", handleNewPost).Methods("POST")
		rtr.HandleFunc(SERVICE_PATH+"/post/{[0-9]+}/permissions", handlePostPermissions).Methods("GET")
		rtr.HandleFunc(SERVICE_PATH+"/post/{[0-9]+}/edit", handlePostEdit).Methods("PUT")
		rtr.HandleFunc(SERVICE_PATH+"/post/{[0-9]+}/delete", handlePostDelete).Methods("DELETE")
		rtr.HandleFunc(SERVICE_PATH+"/user/{[0-9]+}", handleUserInfo).Methods("GET")
		rtr.HandleFunc("/quest/{[0-9]+}/", handleViewQuest).Methods("GET")*/
	//rtr.HandleFunc("/quest/{qid:[0-9]+}/", handleViewQuest).Methods("GET")

	rtr.HandleFunc("/quest/{qid:[0-9]+}/", serveStatic)
	rtr.PathPrefix("/").Handler(http.StripPrefix("/", http.FileServer(http.Dir("static/"))))
	http.Handle("/", rtr)

	host, _ := os.Hostname()
	if host != devMachine {
		fmt.Println("listening on encrypted " + PORT)
		cert := "/etc/letsencrypt/live/www.questlog.net/fullchain.pem"
		prv_key := "/etc/letsencrypt/live/www.questlog.net/privkey.pem"
		http.ListenAndServeTLS(PORT, cert, prv_key, context.ClearHandler(http.DefaultServeMux))
	} else {
		fmt.Println("listening on non-SSL " + PORT)
		http.ListenAndServe(PORT, context.ClearHandler(http.DefaultServeMux))
	}

}
