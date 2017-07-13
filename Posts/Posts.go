package Posts

import (
  "log"
  _ "github.com/go-sql-driver/mysql"
  "bitbucket.org/holodog/questlog/DBUtils"
  "github.com/kennygrant/sanitize"
  "regexp"
  "strings"
  "strconv"
  "math/rand"
  "time"
  "golang.org/x/crypto/ripemd160"
  "encoding/hex"
)

type Post struct {
  Pid int `json:"pid"`
  Qid int `json:"qid"`
  Uid int `json:"uid"`
  Cid int `json:"cid"`
  Poster string `json:"poster"`
  Text string `json:"text"`
  Stamp int `json:"stamp"`
  GmPost bool `json:"gmPost"`
}

type Character struct {
  Cid int `json:"cid"`
  Char_name string `json:"name"`
  Uid int `json:"uid"`
}

type PostPermissions struct {
  Characters []Character `json:"characters"`
  Gm bool `json:"gm"`
}

func random(min, max int) int {
  rand.Seed(time.Now().Unix())
  return rand.Intn(max - min) + min
}

func restoreDiceRolls(pid int, text string) string {
  rgx, _ := regexp.Compile(`\[DICE_ROLL\](\S+)\[/DICE_ROLL\]`)
  var stored_rolls []string
  db := DBUtils.OpenDB()
  for _, match := range rgx.FindStringSubmatch(text) {
    var roll int
    var dieType string
    rows, err := db.Query("select roll,type from rolls where pid = ? and location_hash = ?", pid, match)
    if (err != nil) {
      log.Fatal("Error checking gm")
    }
    for rows.Next() {
      err = rows.Scan(&roll, &dieType)
      if err != nil {
        log.Fatal(err)
      }
      finalRoll := "<div class=\"roll\" data-id=\"" + match + "\">*** " + dieType + " roll:" + strconv.Itoa(roll) + " ***</div>"
      text = rgx.ReplaceAllString(text, finalRoll)
      stored_rolls = append(stored_rolls, match)
    }
  }
  var notStr = "'0'"
  for i:=0; i < len(stored_rolls); i++ {
    notStr += ",'" + stored_rolls[i] + "'";
  }
  rows, err := db.Query("SELECT roll,type,location_hash FROM rolls WHERE location_hash NOT IN (" + notStr + ") AND pid = ?", pid)
  if err != nil {
    log.Fatal(err)
  }
  for rows.Next() {
    var roll int
    var dieType string
    var hash string
    rows.Scan(&roll, &dieType, &hash)
    text += "<div class=\"roll\" data-id=\"" + hash + "\">*** " + dieType + " roll:" + strconv.Itoa(roll) + " ***</div>"
  }
  log.Println("--------------")
  log.Println(text)
  log.Println("--------------")
  DBUtils.CloseDB(db)
  return text
}

func findAndGenerateDiceRolls(pid int, text string) string {
  rgx, _ := regexp.Compile(`(?i)/r(oll)?(\s*)(\d+d\d+)`)
  for index, match := range rgx.FindStringSubmatch(text) {
    if (index == 3) {
      var results = 0
      var hexed = ""
      var amount int
      var diceType int
      pos := strings.Split(match, "d")
      amount, err := strconv.Atoi(pos[0])
      if err != nil {
        log.Fatal("dice amount not int")
      }
      diceType, err = strconv.Atoi(pos[1])
      if err != nil {
        log.Fatal("dice type not int")
      }
      for i:=0; i < amount; i++ {
        results += random(1, diceType)
      }
      hash := ripemd160.New()
      hashNum := pid + random(1,999) + int(time.Now().Unix())
      hash.Write([]byte(strconv.Itoa(hashNum)))
      hexed = hex.EncodeToString(hash.Sum(nil))
      hexedStr := "<div class=\"roll\" data-id=\"" + hexed + "\">*** " + match + " roll:" + strconv.Itoa(results) + " ***</div>"
      db := DBUtils.OpenDB();
      stmt, err := db.Prepare("INSERT INTO rolls (pid,roll,type,location_hash) VALUES(?,?,?,?)")
      if (err != nil) {
        log.Fatal(err)
      }
      defer stmt.Close()
      _, err = stmt.Exec(pid, results, match, hexed)
      if (err != nil) {
        log.Fatal(err)
      }
      DBUtils.CloseDB(db)
      text = rgx.ReplaceAllString(text, hexedStr)
    }
  }
  return text
}



func EditPost(pid int, text string) Post {
  text = restoreDiceRolls(pid, text)
  text = findAndGenerateDiceRolls(pid, text)
  db := DBUtils.OpenDB();
  stmt, err := db.Prepare("update posts set post_text=? where pid=?")
  if (err != nil) {
    log.Fatal("can't update post")
  }
  defer stmt.Close()
  _, err = stmt.Exec(text, pid)
  if (err != nil) {
    log.Fatal("can't update post")
  }
  DBUtils.CloseDB(db)
  return GetPost(pid)
}

func DeletePost(pid int) bool {
  db := DBUtils.OpenDB();
  stmt, err := db.Prepare("delete from posts where pid=?")
  if (err != nil) {
    return false
  }
  defer stmt.Close()
  _, err = stmt.Exec(pid)
  if (err != nil) {
    return false
  }
  stmt, err = db.Prepare("delete from rolls where pid=?")
  if (err != nil) {
    log.Println("error deleting roll for post")
  }
  defer stmt.Close()
  _, err = stmt.Exec(pid)
  if (err != nil) {
    log.Println("error deleting roll for post")
  }
  DBUtils.CloseDB(db)
  return true
}

func GetPostPermissions(qid int, uid int) PostPermissions {
  db := DBUtils.OpenDB();
  permissions := PostPermissions{}

  db.QueryRow("select if (count(qid), 'true', 'false') from quests where qid=? and uid=?", qid, uid).Scan(&permissions.Gm)

  rows, err := db.Query("select c.cid, c.char_name from characters as c, quest_members as qm where qm.qid=? and qm.cid=c.cid and c.uid=?", qid, uid)
  if (err != nil) {
    log.Fatal("Error checking gm")
  }
  var characters []Character
  for rows.Next() {
    character := Character{}
    err = rows.Scan(&character.Cid, &character.Char_name)
    if err != nil {
      log.Fatal(err)
    }
    characters = append(characters, character)
  }
  permissions.Characters = characters
  DBUtils.CloseDB(db)
  return permissions
}


func sanitizeTextForDB(text string) string {
  text = sanitize.Accents(text)
  text = sanitize.HTML(text)
  text = strings.Replace(text,"\n","<br>",-1)
  var re = regexp.MustCompile(`\[b\](.+)?\[\/b\]`)
  text = re.ReplaceAllString(text, `<b>$1</b>`)
  re = regexp.MustCompile(`\[i\](.+)?\[\/i\]`)
  text = re.ReplaceAllString(text, `<i>$1</i>`)
  re = regexp.MustCompile(`\[u\](.+)?\[\/u\]`)
  text = re.ReplaceAllString(text, `<u>$1</u>`)
  return text
}


func CreatePost(qid int, uid int, cid int, text string) Post {
  text = sanitizeTextForDB(text)
  db := DBUtils.OpenDB();
  stmt, err := db.Prepare("INSERT INTO posts (qid,cid,uid,post_text,post_status,post_date) VALUES(?,?,?,?,0,now())")
  if (err != nil) {
    log.Fatal(err)
  }
  defer stmt.Close()
  res, err := stmt.Exec(qid,cid,uid,text)
  if (err != nil) {
    log.Fatal(err)
  }
  id, err := res.LastInsertId()
  if (err != nil) {
    log.Fatal(err)
  }
  DBUtils.CloseDB(db)
  EditPost(int(id), findAndGenerateDiceRolls(int(id), text))
  return GetPost(int(id))
}

func GetPost(pid int) Post {
  db := DBUtils.OpenDB();
  post := Post{}
  post.Pid = pid
  db.QueryRow("select qid,uid,cid,UNIX_TIMESTAMP(post_date),post_text from posts where pid=?", pid).Scan(&post.Qid,&post.Uid,&post.Cid,&post.Stamp,&post.Text)
  if post.Cid == 0 {
    db.QueryRow("select login_name from users where uid = ?", post.Uid).Scan(&post.Poster)
    post.Poster += " - GM"
  } else {
    db.QueryRow("select char_name from characters where cid = ?", post.Cid).Scan(&post.Poster) 
  }
  DBUtils.CloseDB(db)
  return post
}

func GetPosts(qid int, start int, length int, order string) []Post {
  var posts []Post
  db := DBUtils.OpenDB();
  rows, err := db.Query("select pid,qid,uid,cid,post_text,UNIX_TIMESTAMP(post_date) from posts WHERE qid = ? ORDER BY post_date " + order + " LIMIT ?, ?", qid, start, length)
  if err != nil {
    log.Fatal(err)
  }
  defer rows.Close()
  for rows.Next() {
    post := Post{}
    err := rows.Scan(&post.Pid, &post.Qid, &post.Uid, &post.Cid, &post.Text, &post.Stamp)
    if err != nil {
      log.Fatal(err)
    }
    if post.Cid == 0 {
      db.QueryRow("select login_name from users where uid = ?", post.Uid).Scan(&post.Poster)
      post.GmPost = true
      post.Poster += " - GM"
    } else {
      post.GmPost = false
      db.QueryRow("select char_name from characters where cid = ?", post.Cid).Scan(&post.Poster) 
    }
    posts = append(posts, post)
  }
  DBUtils.CloseDB(db)
  return posts
}