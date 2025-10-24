
-- Foreign Key desteğini aktif et
PRAGMA foreign_keys = ON;

--
-- Tablo: Companies (Otobüs Firmaları)
--
CREATE TABLE Companies (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

--
-- Tablo: Users (Kullanıcılar: Admin, Firma Admin, User)
--
CREATE TABLE Users (
    id TEXT PRIMARY KEY,
    fullname TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('Admin', 'Firma Admin', 'User')),
    company_id TEXT, -- Sadece 'Firma Admin' rolü için kullanılır
    balance REAL NOT NULL DEFAULT 0.0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Companies(id)
);

--
-- Tablo: Buses (Otobüs Seferleri)
--
CREATE TABLE Buses (
    id TEXT PRIMARY KEY,
    company_id TEXT NOT NULL,
    departure_location TEXT NOT NULL,
    arrival_location TEXT NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price REAL NOT NULL,
    total_seats INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Companies(id)
);

--
-- Tablo: Tickets (Satın Alınan Biletler)
--
CREATE TABLE Tickets (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    bus_id TEXT NOT NULL,
    seat_number INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'ACTIVE' CHECK(status IN ('ACTIVE', 'CANCELLED')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id),
    FOREIGN KEY (bus_id) REFERENCES Buses(id)
);

--
-- Tablo: Coupons (İndirim Kuponları)
--
CREATE TABLE Coupons (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    discount_rate REAL NOT NULL,
    usage_limit INTEGER NOT NULL,
    expiration_date DATETIME NOT NULL,
    company_id TEXT, -- NULL ise tüm firmalarda geçerli (Admin tarafından oluşturulmuş)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Companies(id)
);

