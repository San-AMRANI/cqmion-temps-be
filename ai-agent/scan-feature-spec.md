# 🧠 Backend Scan Logic — Spécification Complète (VERSION PRODUCTION)

## 🚛 Truck Lifecycle Tracking System

---

# 🎯 1. Objectif

Définir **précisément** le comportement du backend lors d’un scan afin de :

* Garantir une **logique métier parfaite**
* Éviter toute incohérence de cycle
* Assurer une **synchronisation totale avec l’application opérateur**
* Gérer automatiquement :

  * création de trajet
  * progression du cycle
  * validation opérateur / localisation
  * gestion des erreurs

---

# 🧠 2. Principe Fondamental

👉 Le backend est **le seul décideur**

Le scan est une simple entrée :

```json id="in5a4f"
{
  "qr_code": "...",
  "device_time": "...",
  "device_id": "..."
}
```

👉 Le backend doit :

1. Identifier le camion
2. Identifier le trajet actif (ou non)
3. Décider de l’action à effectuer
4. Appliquer la transition
5. Retourner l’état final

---

# 🔄 3. Cycle métier (RÉFÉRENCE)

---

## États :

```text id="bxw0mi"
STARTED → ARRIVED_PORT → LEFT_PORT → COMPLETED
```

---

## Règles :

* 1 seul trajet actif par camion
* Aucun saut d’étape
* Aucun retour en arrière
* Scan obligatoire dans l’ordre

---

# 🧩 4. Algorithme COMPLET du Scan

---

## 🧭 Étape 1 — Identification camion

```php id="jvx81c"
$truck = Truck::where('qr_code', $qr)->lockForUpdate()->first();
```

### Vérifications :

* ❌ camion inexistant → ERREUR
* ❌ camion inactif → ERREUR

---

## 🧭 Étape 2 — Récupération trajet actif

```php id="cv6pjl"
$trip = Trip::where('truck_id', $truck->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
```

---

## 🧭 Étape 3 — Détermination du contexte

---

### CAS 1 — Aucun trajet actif

👉 Action attendue = **START**

---

### Conditions :

* opérateur = COMPANY_OPERATOR
* localisation = COMPANY

---

### Si valide :

```php id="95w0yx"
$trip = Trip::create([
  'truck_id' => $truck->id,
  'status' => 'STARTED',
  'started_at' => now(),
  'is_active' => true
]);
```

---

### Sinon :

❌ Refuser scan

---

## 🧭 Étape 4 — Si trajet actif existe

---

### Déterminer prochaine étape :

```php id="r2e13g"
$next = match($trip->status) {
  'STARTED' => 'ARRIVED_PORT',
  'ARRIVED_PORT' => 'LEFT_PORT',
  'LEFT_PORT' => 'COMPLETED',
};
```

---

## 🧭 Étape 5 — Validation opérateur

---

### Règles :

| Étape                    | Rôle requis      | Localisation |
| ------------------------ | ---------------- | ------------ |
| STARTED → ARRIVED_PORT   | PORT_OPERATOR    | PORT         |
| ARRIVED_PORT → LEFT_PORT | PORT_OPERATOR    | PORT         |
| LEFT_PORT → COMPLETED    | COMPANY_OPERATOR | COMPANY      |

---

### Si invalide :

❌ Refuser scan

---

## 🧭 Étape 6 — Idempotency (ANTI DOUBLE SCAN)

---

### Vérifier :

```php id="ttn0r8"
ScanLog::where('trip_id', $trip->id)
       ->where('action', $next)
       ->whereBetween('scanned_at', [now()->subSeconds(10), now()])
```

👉 Si trouvé → rejeter

---

## 🧭 Étape 7 — Appliquer transition

---

### ARRIVED_PORT :

```php id="s1h0ci"
$trip->update([
  'status' => 'ARRIVED_PORT',
  'arrived_port_at' => now()
]);
```

---

### LEFT_PORT :

```php id="83rfd6"
$trip->update([
  'status' => 'LEFT_PORT',
  'left_port_at' => now()
]);
```

---

### COMPLETED :

```php id="v2qk1q"
$trip->update([
  'status' => 'COMPLETED',
  'completed_at' => now(),
  'is_active' => false
]);
```

---

## 🧭 Étape 8 — Enregistrement log

---

```php id="7z8h3m"
ScanLog::create([
  'truck_id' => $truck->id,
  'trip_id' => $trip->id,
  'user_id' => $user->id,
  'action' => $next,
  'location' => $user->location,
  'scanned_at' => now(),
  'device_id' => $deviceId
]);
```

---

## 🧭 Étape 9 — Réponse API

---

```json id="vq3k3w"
{
  "success": true,
  "data": {
    "status": "SUCCESS",
    "current_step": "ARRIVED_PORT",
    "next_expected_step": "LEFT_PORT",
    "trip_summary": {
      "truck": {
        "id": 1,
        "registration_number": "123-A-456"
      },
      "status": "ARRIVED_PORT",
      "timestamps": {
        "started_at": "...",
        "arrived_port_at": "...",
        "left_port_at": null,
        "completed_at": null
      }
    }
  }
}
```

---

# ⏱️ 5. Gestion des timestamps

---

## Règles :

* Toujours utiliser :

```php id="o5j5o8"
now()
```

* `device_time` = informatif uniquement (optionnel)

---

# 🚨 6. Gestion des erreurs

---

## Cas :

* Camion introuvable
* Camion inactif
* Mauvais opérateur
* Mauvaise localisation
* Séquence invalide
* Double scan

---

## Format :

```json id="q6rm1p"
{
  "success": false,
  "message": "Séquence invalide",
  "errors": {
    "scan": "Transition non autorisée"
  }
}
```

---

# 🔐 7. Sécurité et concurrence

---

## Obligatoire :

* DB transaction
* lockForUpdate()
* unique (truck_id, is_active)
* unique scan constraint

---

# 📡 8. Événements

---

Déclencher :

* TripStarted
* ArrivedAtPort
* LeftPort
* TripCompleted

---

# 📊 9. Données utilisées par Flutter

---

## Doivent être toujours présentes :

* current_step
* next_expected_step
* truck.registration_number
* timestamps

---

# 🚀 10. Résultat attendu

---

👉 Backend capable de :

* créer trajet automatiquement
* continuer trajet existant
* refuser erreurs
* garantir cohérence

---

👉 Flutter devient :

* ultra simple
* sans logique métier

---

# 🧠 CONCLUSION

---

👉 Ce service est le **cœur du système**

S’il est bien fait :

* aucune erreur terrain
* aucune confusion opérateur
* système robuste

---

## ✅ FIN — SCAN LOGIC SPEC
