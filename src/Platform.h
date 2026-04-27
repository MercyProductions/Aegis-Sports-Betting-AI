#pragma once

#include <filesystem>
#include <string>
#include <vector>

namespace aegis
{
    constexpr int kConfigSchemaVersion = 2;

    struct HttpResponse
    {
        int status_code = 0;
        std::string body;
        std::string raw_headers;
        std::vector<std::string> set_cookies;
        std::string error;
    };

    struct Config
    {
        int config_schema_version = kConfigSchemaVersion;
        int loaded_config_schema_version = kConfigSchemaVersion;
        bool migrated_config = false;
        std::string auth_base_url = "http://127.0.0.1:8000";
        std::string login_path = "/api/auth/login.php";
        std::string sports_endpoint = "/api/sports-live.php";
        std::string website_path = "/sports-betting.php";
        std::string odds_api_key;
        std::string kalshi_key_id;
        std::string kalshi_private_key;
        std::string favorite_teams;
        std::string favorite_leagues;
        std::string injury_feed_url;
        std::string lineup_feed_url;
        std::string news_feed_url;
        std::string props_feed_url;
        int refresh_seconds = 60;
        int tracked_games = 100;
        int model_count = 12;
        double bankroll_starting_amount = 1000.0;
        double max_ticket_amount = 250.0;
        double daily_exposure_limit = 1000.0;
        int min_ticket_confidence = 58;
        bool paper_only_mode = true;
        bool require_live_confirmation = true;
        bool notifications_enabled = true;
        bool bankroll_analytics_enabled = false;
        bool player_props_enabled = false;
        bool responsible_use_accepted = false;
        bool legal_location_confirmed = false;
        int alert_confidence_threshold = 65;
        bool alert_watchlist_only = false;
        bool alert_line_movement_only = false;
        bool remember_credentials = true;
    };

    struct RememberedCredentials
    {
        bool ok = false;
        std::string username;
        std::string password;
    };

    struct KalshiCredentials
    {
        bool ok = false;
        std::string key_id;
        std::string private_key;
    };

    std::filesystem::path ExecutableDirectory();
    std::filesystem::path AppDataDirectory();
    Config LoadConfig();
    bool SaveConfig(const Config& config);

    std::wstring Utf8ToWide(const std::string& value);
    std::string WideToUtf8(const std::wstring& value);
    std::string GetEnvUtf8(const wchar_t* name);
    std::string JoinUrl(const std::string& base, const std::string& path);
    std::string EscapeJson(const std::string& value);
    std::string Trim(const std::string& value);
    std::string Lower(std::string value);
    std::string Initials(const std::string& value);
    std::string NowTimeLabel();

    HttpResponse HttpGet(const std::string& url, const std::string& cookie_header = "");
    HttpResponse HttpPostJson(const std::string& url, const std::string& body, const std::string& cookie_header = "");
    HttpResponse HttpPostForm(const std::string& url, const std::string& body, const std::string& cookie_header = "");
    std::string CookieHeaderFromSetCookies(const std::vector<std::string>& set_cookies);
    std::string UrlEncode(const std::string& value);

    bool SaveRememberedCredentials(const std::string& username, const std::string& password);
    RememberedCredentials LoadRememberedCredentials();
    void DeleteRememberedCredentials();
    bool SaveOddsApiKey(const std::string& key);
    std::string LoadOddsApiKey();
    void DeleteOddsApiKey();
    bool SaveKalshiCredentials(const std::string& key_id, const std::string& private_key);
    KalshiCredentials LoadKalshiCredentials();
    void DeleteKalshiCredentials();
    void AppendDiagnosticLine(const std::string& line);

    void OpenExternalUrl(const std::string& url);
}
