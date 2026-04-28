# 🔧 Backend Update Specification — Operator Alignment (REQUIRED)

## 🎯 Objectif

Mettre à jour le backend Laravel pour fournir des données **parfaitement adaptées à l’application opérateur Flutter**, afin d’éviter toute logique côté mobile.

👉 Principe :

> Le backend doit retourner **des données directement exploitables par l’UI**

---

# 🚨 1. Problèmes à corriger

---

## ❌ 1.1 `next_expected_step` absent dans `/trips/active`

### Solution

Ajouter un champ calculé dans toutes les réponses de type Trip :

```php
function getNextExpectedStep($status) {
    return match($status) {
        'STARTED' => 'ARRIVED_PORT',
        'ARRIVED_PORT' => 'LEFT_PORT',
        'LEFT_PORT' => 'COMPLETED',
        default => null,
    };
}
```

---

## ❌ 1.2 `registration_number` manquant

### Solution

Inclure relation truck dans TripResource :

```json
{
  "truck": {
    "id": 1,
    "registration_number": "123-A-456"
  }
}
```

---

## ❌ 1.3 `current_location` manquant

### Solution

Ajouter champ calculé :

```php
function resolveLocation($status) {
    return match($status) {
        'STARTED' => 'ON_ROUTE_TO_PORT',
        'ARRIVED_PORT' => 'AT_PORT',
        'LEFT_PORT' => 'RETURNING',
        'COMPLETED' => 'AT_COMPANY',
    };
}
```

---

## ❌ 1.4 Structure non optimisée pour mobile

### Solution

Créer un **TripResource dédié opérateur** :

```json
{
  "id": 1,
  "status": "ARRIVED_PORT",
  "next_expected_step": "LEFT_PORT",
  "current_location": "AT_PORT",
  "last_scan_at": "...",
  "truck": {
    "id": 1,
    "registration_number": "123-A-456"
  }
}
```

---

# 🔄 2. Endpoint à modifier

---

## ✅ `/api/trips/active`

### DOIT retourner :

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "status": "STARTED",
      "next_expected_step": "ARRIVED_PORT",
      "current_location": "ON_ROUTE_TO_PORT",
      "last_scan_at": "...",
      "truck": {
        "id": 1,
        "registration_number": "123-A-456"
      }
    }
  ]
}
```

---

## ✅ `/api/operator/last-scans`

### DOIT retourner :

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "action": "ARRIVED_PORT",
      "scanned_at": "...",
      "truck": {
        "id": 1,
        "registration_number": "123-A-456"
      }
    }
  ]
}
```

---

## ✅ `/api/scan` (update mineur)

Ajouter :

```json
"truck": {
  "id": 1,
  "registration_number": "123-A-456"
}
```

dans `trip_summary`

---

# 🧠 3. Bonnes pratiques à respecter

---

## ✅ Toujours utiliser API Resources

* `TripResource`
* `OperatorTripResource`
* `ScanLogResource`

---

## ✅ Ne jamais exposer données brutes

Toujours transformer :

* status enrichi
* relations incluses
* champs calculés

---

## ✅ Cohérence globale

Tous les endpoints doivent retourner :

```json
{
  "success": true,
  "data": ...
}
```

---

# 🚀 4. Résultat attendu

---

👉 Flutter pourra :

* afficher directement les données
* sans logique métier
* sans mapping complexe

---

👉 Backend devient :

* source unique de vérité
* parfaitement consommable

---

## ✅ FIN — BACKEND UPDATE
