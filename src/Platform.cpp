#include "Platform.h"

#include "AppVersion.h"

#include <windows.h>
#include <winhttp.h>
#include <wincrypt.h>
#include <shellapi.h>
#include <shlobj.h>

#include <algorithm>
#include <chrono>
#include <cctype>
#include <cstdlib>
#include <fstream>
#include <iomanip>
#include <sstream>

namespace aegis
{
    namespace
    {
        std::filesystem::path RememberFile()
        {
            return AppDataDirectory() / "remember.dat";
        }

        std::filesystem::path OddsApiKeyFile()
        {
            return AppDataDirectory() / "odds-api-key.dat";
        }

        std::filesystem::path KalshiCredentialsFile()
        {
            return AppDataDirectory() / "kalshi-credentials.dat";
        }

        bool ParseEnabled(const std::string& value, bool default_value)
        {
            const std::string lower = Lower(Trim(value));
            if (lower == "true" || lower == "1" || lower == "yes" || lower == "on")
                return true;
            if (lower == "false" || lower == "0" || lower == "no" || lower == "off")
                return false;
            return default_value;
        }

        void NormalizeConfig(Config& config)
        {
            config.config_schema_version = kConfigSchemaVersion;
            config.refresh_seconds = std::max(5, config.refresh_seconds);
            config.tracked_games = std::clamp(config.tracked_games, 12, 160);
            config.model_count = std::clamp(config.model_count, 2, 32);
            config.bankroll_starting_amount = std::clamp(config.bankroll_starting_amount, 1.0, 10000000.0);
            config.max_ticket_amount = std::clamp(config.max_ticket_amount, 1.0, 100000.0);
            config.daily_exposure_limit = std::clamp(config.daily_exposure_limit, 1.0, 1000000.0);
            config.min_ticket_confidence = std::clamp(config.min_ticket_confidence, 1, 99);
            config.alert_confidence_threshold = std::clamp(config.alert_confidence_threshold, 50, 99);
            if (!config.paper_only_mode && !config.require_live_confirmation)
                config.require_live_confirmation = true;
        }

        std::string HexEncode(const std::vector<unsigned char>& bytes)
        {
            std::ostringstream stream;
            stream << std::hex << std::setfill('0');
            for (const unsigned char b : bytes)
                stream << std::setw(2) << static_cast<int>(b);
            return stream.str();
        }

        std::vector<unsigned char> HexDecode(const std::string& text)
        {
            std::vector<unsigned char> bytes;
            if (text.size() % 2 != 0)
                return bytes;
            bytes.reserve(text.size() / 2);
            for (size_t i = 0; i < text.size(); i += 2)
            {
                const std::string part = text.substr(i, 2);
                char* end = nullptr;
                const long value = std::strtol(part.c_str(), &end, 16);
                if (end == nullptr || *end != '\0' || value < 0 || value > 255)
                    return {};
                bytes.push_back(static_cast<unsigned char>(value));
            }
            return bytes;
        }

        std::vector<unsigned char> ProtectString(const std::string& value)
        {
            DATA_BLOB in{};
            in.pbData = reinterpret_cast<BYTE*>(const_cast<char*>(value.data()));
            in.cbData = static_cast<DWORD>(value.size());

            DATA_BLOB out{};
            if (!CryptProtectData(&in, L"Aegis Sports Betting AI", nullptr, nullptr, nullptr, CRYPTPROTECT_UI_FORBIDDEN, &out))
                return {};

            std::vector<unsigned char> bytes(out.pbData, out.pbData + out.cbData);
            LocalFree(out.pbData);
            return bytes;
        }

        std::string UnprotectString(const std::vector<unsigned char>& bytes)
        {
            if (bytes.empty())
                return {};

            DATA_BLOB in{};
            in.pbData = const_cast<BYTE*>(bytes.data());
            in.cbData = static_cast<DWORD>(bytes.size());
            DATA_BLOB out{};
            if (!CryptUnprotectData(&in, nullptr, nullptr, nullptr, nullptr, CRYPTPROTECT_UI_FORBIDDEN, &out))
                return {};

            std::string value(reinterpret_cast<char*>(out.pbData), reinterpret_cast<char*>(out.pbData) + out.cbData);
            LocalFree(out.pbData);
            return value;
        }

        std::vector<std::string> SplitLines(const std::string& value)
        {
            std::vector<std::string> lines;
            std::stringstream stream(value);
            std::string line;
            while (std::getline(stream, line))
            {
                if (!line.empty() && line.back() == '\r')
                    line.pop_back();
                lines.push_back(line);
            }
            return lines;
        }

        HttpResponse SendWinHttpRequest(const std::wstring& method, const std::string& url, const std::string* body, const std::string& cookie_header, const std::wstring& content_type = L"application/json")
        {
            HttpResponse response;
            const std::wstring wide_url = Utf8ToWide(url);

            URL_COMPONENTS components{};
            components.dwStructSize = sizeof(components);
            components.dwSchemeLength = static_cast<DWORD>(-1);
            components.dwHostNameLength = static_cast<DWORD>(-1);
            components.dwUrlPathLength = static_cast<DWORD>(-1);
            components.dwExtraInfoLength = static_cast<DWORD>(-1);

            if (!WinHttpCrackUrl(wide_url.c_str(), 0, 0, &components))
            {
                response.error = "Failed to parse URL.";
                return response;
            }

            const std::wstring host(components.lpszHostName, components.dwHostNameLength);
            std::wstring path(components.lpszUrlPath, components.dwUrlPathLength);
            if (components.dwExtraInfoLength > 0)
                path.append(components.lpszExtraInfo, components.dwExtraInfoLength);

            HINTERNET session = WinHttpOpen(L"AegisSportsBettingAI/1.0", WINHTTP_ACCESS_TYPE_DEFAULT_PROXY, WINHTTP_NO_PROXY_NAME, WINHTTP_NO_PROXY_BYPASS, 0);
            if (!session)
            {
                response.error = "Failed to open HTTP session.";
                return response;
            }

            WinHttpSetTimeouts(session, 5000, 6000, 8000, 12000);

            HINTERNET connection = WinHttpConnect(session, host.c_str(), components.nPort, 0);
            if (!connection)
            {
                response.error = "Failed to connect to HTTP host.";
                WinHttpCloseHandle(session);
                return response;
            }

            const DWORD flags = components.nScheme == INTERNET_SCHEME_HTTPS ? WINHTTP_FLAG_SECURE : 0;
            HINTERNET request = WinHttpOpenRequest(connection, method.c_str(), path.c_str(), nullptr, WINHTTP_NO_REFERER, WINHTTP_DEFAULT_ACCEPT_TYPES, flags);
            if (!request)
            {
                response.error = "Failed to create HTTP request.";
                WinHttpCloseHandle(connection);
                WinHttpCloseHandle(session);
                return response;
            }

            std::wstring headers = L"Accept: application/json\r\n";
            if (body != nullptr)
            {
                headers += L"Content-Type: ";
                headers += content_type;
                headers += L"\r\n";
            }
            if (!cookie_header.empty())
            {
                headers += L"Cookie: ";
                headers += Utf8ToWide(cookie_header);
                headers += L"\r\n";
            }

            const void* body_data = body == nullptr || body->empty() ? WINHTTP_NO_REQUEST_DATA : body->data();
            const DWORD body_size = body == nullptr ? 0u : static_cast<DWORD>(body->size());
            const BOOL sent = WinHttpSendRequest(
                request,
                headers.c_str(),
                static_cast<DWORD>(-1L),
                const_cast<void*>(body_data),
                body_size,
                body_size,
                0);

            if (!sent || !WinHttpReceiveResponse(request, nullptr))
            {
                response.error = "HTTP request failed.";
                WinHttpCloseHandle(request);
                WinHttpCloseHandle(connection);
                WinHttpCloseHandle(session);
                return response;
            }

            DWORD status_code = 0;
            DWORD status_size = sizeof(status_code);
            WinHttpQueryHeaders(request, WINHTTP_QUERY_STATUS_CODE | WINHTTP_QUERY_FLAG_NUMBER, WINHTTP_HEADER_NAME_BY_INDEX, &status_code, &status_size, WINHTTP_NO_HEADER_INDEX);
            response.status_code = static_cast<int>(status_code);

            DWORD raw_size = 0;
            WinHttpQueryHeaders(request, WINHTTP_QUERY_RAW_HEADERS_CRLF, WINHTTP_HEADER_NAME_BY_INDEX, nullptr, &raw_size, WINHTTP_NO_HEADER_INDEX);
            if (GetLastError() == ERROR_INSUFFICIENT_BUFFER && raw_size > 0)
            {
                std::wstring raw(raw_size / sizeof(wchar_t), L'\0');
                if (WinHttpQueryHeaders(request, WINHTTP_QUERY_RAW_HEADERS_CRLF, WINHTTP_HEADER_NAME_BY_INDEX, raw.data(), &raw_size, WINHTTP_NO_HEADER_INDEX))
                {
                    raw.resize(raw_size / sizeof(wchar_t));
                    response.raw_headers = WideToUtf8(raw);
                    for (const std::string& line : SplitLines(response.raw_headers))
                    {
                        const std::string lowered = Lower(line);
                        if (lowered.rfind("set-cookie:", 0) == 0)
                            response.set_cookies.push_back(Trim(line.substr(11)));
                    }
                }
            }

            DWORD cookie_size = 0;
            WinHttpQueryHeaders(request, WINHTTP_QUERY_SET_COOKIE, WINHTTP_HEADER_NAME_BY_INDEX, nullptr, &cookie_size, WINHTTP_NO_HEADER_INDEX);
            if (GetLastError() == ERROR_INSUFFICIENT_BUFFER && cookie_size > 0)
            {
                std::wstring cookies(cookie_size / sizeof(wchar_t), L'\0');
                if (WinHttpQueryHeaders(request, WINHTTP_QUERY_SET_COOKIE, WINHTTP_HEADER_NAME_BY_INDEX, cookies.data(), &cookie_size, WINHTTP_NO_HEADER_INDEX))
                {
                    cookies.resize(cookie_size / sizeof(wchar_t));
                    for (const std::string& line : SplitLines(WideToUtf8(cookies)))
                    {
                        std::string cookie = Trim(line);
                        if (!cookie.empty())
                            response.set_cookies.push_back(cookie);
                    }
                }
            }

            DWORD available = 0;
            while (WinHttpQueryDataAvailable(request, &available) && available > 0)
            {
                std::string chunk(static_cast<size_t>(available), '\0');
                DWORD downloaded = 0;
                if (!WinHttpReadData(request, chunk.data(), available, &downloaded) || downloaded == 0)
                    break;
                chunk.resize(downloaded);
                response.body += chunk;
            }

            WinHttpCloseHandle(request);
            WinHttpCloseHandle(connection);
            WinHttpCloseHandle(session);
            return response;
        }
    }

    std::filesystem::path ExecutableDirectory()
    {
        wchar_t buffer[MAX_PATH]{};
        GetModuleFileNameW(nullptr, buffer, MAX_PATH);
        return std::filesystem::path(buffer).parent_path();
    }

    std::filesystem::path AppDataDirectory()
    {
        PWSTR raw = nullptr;
        std::filesystem::path base;
        if (SUCCEEDED(SHGetKnownFolderPath(FOLDERID_LocalAppData, 0, nullptr, &raw)) && raw != nullptr)
        {
            base = raw;
            CoTaskMemFree(raw);
        }
        else
        {
            base = ExecutableDirectory();
        }

        std::filesystem::path path = base / "Aegis" / "Sports Betting AI";
        std::error_code ec;
        std::filesystem::create_directories(path, ec);
        return path;
    }

    Config LoadConfig()
    {
        Config config;
        const std::filesystem::path path = ExecutableDirectory() / "AegisSportsBettingAI.config.ini";
        std::ifstream file(path);
        if (!file)
        {
            const std::filesystem::path dev_path = std::filesystem::current_path() / "AegisSportsBettingAI.config.ini";
            file.open(dev_path);
        }
        if (!file)
        {
            config.odds_api_key = LoadOddsApiKey();
            NormalizeConfig(config);
            return config;
        }

        bool saw_schema_version = false;
        bool migrated_plain_secret = false;
        std::string line;
        while (std::getline(file, line))
        {
            line = Trim(line);
            if (line.empty() || line[0] == '[' || line[0] == '#' || line[0] == ';')
                continue;
            const size_t eq = line.find('=');
            if (eq == std::string::npos)
                continue;

            const std::string key = Lower(Trim(line.substr(0, eq)));
            const std::string value = Trim(line.substr(eq + 1));
            if (key == "config_schema_version" || key == "schema_version")
            {
                const int parsed = std::atoi(value.c_str());
                config.loaded_config_schema_version = parsed;
                config.config_schema_version = parsed;
                saw_schema_version = true;
            }
            else if (key == "auth_base_url")
                config.auth_base_url = value;
            else if (key == "login_path")
                config.login_path = value;
            else if (key == "sports_endpoint")
                config.sports_endpoint = value;
            else if (key == "website_path")
                config.website_path = value;
            else if (key == "odds_api_key")
                config.odds_api_key = value;
            else if (key == "kalshi_key_id")
                config.kalshi_key_id = value;
            else if (key == "kalshi_private_key")
                config.kalshi_private_key = value;
            else if (key == "favorite_teams")
                config.favorite_teams = value;
            else if (key == "favorite_leagues")
                config.favorite_leagues = value;
            else if (key == "injury_feed_url")
                config.injury_feed_url = value;
            else if (key == "lineup_feed_url")
                config.lineup_feed_url = value;
            else if (key == "news_feed_url")
                config.news_feed_url = value;
            else if (key == "props_feed_url")
                config.props_feed_url = value;
            else if (key == "refresh_seconds")
                config.refresh_seconds = std::max(5, std::atoi(value.c_str()));
            else if (key == "tracked_games")
                config.tracked_games = std::clamp(std::atoi(value.c_str()), 12, 160);
            else if (key == "model_count")
                config.model_count = std::clamp(std::atoi(value.c_str()), 2, 32);
            else if (key == "bankroll_starting_amount")
                config.bankroll_starting_amount = std::clamp(std::atof(value.c_str()), 1.0, 10000000.0);
            else if (key == "max_ticket_amount")
                config.max_ticket_amount = std::clamp(std::atof(value.c_str()), 1.0, 100000.0);
            else if (key == "daily_exposure_limit")
                config.daily_exposure_limit = std::clamp(std::atof(value.c_str()), 1.0, 1000000.0);
            else if (key == "min_ticket_confidence")
                config.min_ticket_confidence = std::clamp(std::atoi(value.c_str()), 1, 99);
            else if (key == "paper_only_mode")
                config.paper_only_mode = ParseEnabled(value, true);
            else if (key == "require_live_confirmation")
                config.require_live_confirmation = ParseEnabled(value, true);
            else if (key == "notifications_enabled")
                config.notifications_enabled = ParseEnabled(value, true);
            else if (key == "bankroll_analytics_enabled")
                config.bankroll_analytics_enabled = ParseEnabled(value, false);
            else if (key == "player_props_enabled")
                config.player_props_enabled = ParseEnabled(value, false);
            else if (key == "responsible_use_accepted")
                config.responsible_use_accepted = ParseEnabled(value, false);
            else if (key == "legal_location_confirmed")
                config.legal_location_confirmed = ParseEnabled(value, false);
            else if (key == "alert_confidence_threshold")
                config.alert_confidence_threshold = std::clamp(std::atoi(value.c_str()), 50, 99);
            else if (key == "alert_watchlist_only")
                config.alert_watchlist_only = ParseEnabled(value, true);
            else if (key == "alert_line_movement_only")
                config.alert_line_movement_only = ParseEnabled(value, true);
            else if (key == "remember_credentials")
                config.remember_credentials = ParseEnabled(value, true);
        }

        if (!saw_schema_version)
        {
            config.loaded_config_schema_version = 0;
            config.migrated_config = true;
        }
        else if (config.loaded_config_schema_version < kConfigSchemaVersion)
        {
            config.migrated_config = true;
        }

        const std::string secure_key = LoadOddsApiKey();
        if (!secure_key.empty())
        {
            config.odds_api_key = secure_key;
        }
        else if (!Trim(config.odds_api_key).empty())
        {
            SaveOddsApiKey(config.odds_api_key);
            migrated_plain_secret = true;
        }
        const KalshiCredentials kalshi = LoadKalshiCredentials();
        if (kalshi.ok)
        {
            config.kalshi_key_id = kalshi.key_id;
            config.kalshi_private_key = kalshi.private_key;
        }
        else if (!Trim(config.kalshi_key_id).empty() || !Trim(config.kalshi_private_key).empty())
        {
            SaveKalshiCredentials(config.kalshi_key_id, config.kalshi_private_key);
            migrated_plain_secret = true;
        }

        NormalizeConfig(config);
        if (config.migrated_config || migrated_plain_secret)
        {
            SaveConfig(config);
            AppendDiagnosticLine("config_migration loaded_schema=" + std::to_string(config.loaded_config_schema_version) +
                " current_schema=" + std::to_string(kConfigSchemaVersion) +
                " plaintext_secret_migrated=" + std::string(migrated_plain_secret ? "1" : "0"));
        }

        return config;
    }

    bool SaveConfig(const Config& config)
    {
        const std::filesystem::path path = ExecutableDirectory() / "AegisSportsBettingAI.config.ini";
        const std::filesystem::path temp_path = path.string() + ".tmp";
        std::ofstream file(temp_path, std::ios::trunc);
        if (!file)
            return false;

        file << "[meta]\n";
        file << "config_schema_version=" << kConfigSchemaVersion << "\n";
        file << "app_version=" << kAppVersion << "\n\n";

        file << "[auth]\n";
        file << "auth_base_url=" << config.auth_base_url << "\n";
        file << "login_path=" << config.login_path << "\n";
        file << "sports_endpoint=" << config.sports_endpoint << "\n";
        file << "website_path=" << config.website_path << "\n\n";

        if (!Trim(config.odds_api_key).empty())
            SaveOddsApiKey(config.odds_api_key);
        else
            DeleteOddsApiKey();
        if (!Trim(config.kalshi_key_id).empty() || !Trim(config.kalshi_private_key).empty())
            SaveKalshiCredentials(config.kalshi_key_id, config.kalshi_private_key);
        else
            DeleteKalshiCredentials();

        file << "[data]\n";
        file << "odds_api_key=\n";
        file << "kalshi_key_id=\n";
        file << "kalshi_private_key=\n";
        file << "favorite_teams=" << config.favorite_teams << "\n";
        file << "favorite_leagues=" << config.favorite_leagues << "\n";
        file << "injury_feed_url=" << config.injury_feed_url << "\n";
        file << "lineup_feed_url=" << config.lineup_feed_url << "\n";
        file << "news_feed_url=" << config.news_feed_url << "\n";
        file << "props_feed_url=" << config.props_feed_url << "\n";
        file << "tracked_games=" << std::clamp(config.tracked_games, 12, 160) << "\n";
        file << "model_count=" << std::clamp(config.model_count, 2, 32) << "\n\n";

        file << "[app]\n";
        file << "refresh_seconds=" << std::max(5, config.refresh_seconds) << "\n";
        file << "notifications_enabled=" << (config.notifications_enabled ? "true" : "false") << "\n";
        file << "remember_credentials=" << (config.remember_credentials ? "true" : "false") << "\n";
        file << "player_props_enabled=" << (config.player_props_enabled ? "true" : "false") << "\n";
        file << "bankroll_analytics_enabled=" << (config.bankroll_analytics_enabled ? "true" : "false") << "\n";
        file << "responsible_use_accepted=" << (config.responsible_use_accepted ? "true" : "false") << "\n";
        file << "legal_location_confirmed=" << (config.legal_location_confirmed ? "true" : "false") << "\n";
        file << "\n[alerts]\n";
        file << "alert_confidence_threshold=" << std::clamp(config.alert_confidence_threshold, 50, 99) << "\n";
        file << "alert_watchlist_only=" << (config.alert_watchlist_only ? "true" : "false") << "\n";
        file << "alert_line_movement_only=" << (config.alert_line_movement_only ? "true" : "false") << "\n";
        file << "\n[risk]\n";
        file << "bankroll_starting_amount=" << std::clamp(config.bankroll_starting_amount, 1.0, 10000000.0) << "\n";
        file << "max_ticket_amount=" << std::clamp(config.max_ticket_amount, 1.0, 100000.0) << "\n";
        file << "daily_exposure_limit=" << std::clamp(config.daily_exposure_limit, 1.0, 1000000.0) << "\n";
        file << "min_ticket_confidence=" << std::clamp(config.min_ticket_confidence, 1, 99) << "\n";
        file << "paper_only_mode=" << (config.paper_only_mode ? "true" : "false") << "\n";
        file << "require_live_confirmation=" << (config.require_live_confirmation ? "true" : "false") << "\n";
        file.close();
        if (!file)
        {
            std::error_code remove_error;
            std::filesystem::remove(temp_path, remove_error);
            return false;
        }

        if (!MoveFileExW(temp_path.wstring().c_str(), path.wstring().c_str(), MOVEFILE_REPLACE_EXISTING | MOVEFILE_WRITE_THROUGH))
        {
            std::error_code remove_error;
            std::filesystem::remove(temp_path, remove_error);
            return false;
        }
        return true;
    }

    std::wstring Utf8ToWide(const std::string& value)
    {
        if (value.empty())
            return {};
        const int size = MultiByteToWideChar(CP_UTF8, 0, value.data(), static_cast<int>(value.size()), nullptr, 0);
        std::wstring wide(static_cast<size_t>(size), L'\0');
        MultiByteToWideChar(CP_UTF8, 0, value.data(), static_cast<int>(value.size()), wide.data(), size);
        return wide;
    }

    std::string WideToUtf8(const std::wstring& value)
    {
        if (value.empty())
            return {};
        const int size = WideCharToMultiByte(CP_UTF8, 0, value.data(), static_cast<int>(value.size()), nullptr, 0, nullptr, nullptr);
        std::string utf8(static_cast<size_t>(size), '\0');
        WideCharToMultiByte(CP_UTF8, 0, value.data(), static_cast<int>(value.size()), utf8.data(), size, nullptr, nullptr);
        return utf8;
    }

    std::string GetEnvUtf8(const wchar_t* name)
    {
        wchar_t buffer[4096]{};
        const DWORD read = GetEnvironmentVariableW(name, buffer, static_cast<DWORD>(std::size(buffer)));
        if (read == 0 || read >= std::size(buffer))
            return {};
        return WideToUtf8(buffer);
    }

    std::string JoinUrl(const std::string& base, const std::string& path)
    {
        if (path.rfind("http://", 0) == 0 || path.rfind("https://", 0) == 0)
            return path;
        if (base.empty())
            return path;
        const bool base_slash = base.back() == '/';
        const bool path_slash = !path.empty() && path.front() == '/';
        if (base_slash && path_slash)
            return base.substr(0, base.size() - 1) + path;
        if (!base_slash && !path_slash)
            return base + "/" + path;
        return base + path;
    }

    std::string EscapeJson(const std::string& value)
    {
        std::string out;
        out.reserve(value.size() + 8);
        for (const char c : value)
        {
            switch (c)
            {
            case '\\': out += "\\\\"; break;
            case '"': out += "\\\""; break;
            case '\n': out += "\\n"; break;
            case '\r': out += "\\r"; break;
            case '\t': out += "\\t"; break;
            default:
                if (static_cast<unsigned char>(c) < 0x20)
                    out += ' ';
                else
                    out += c;
                break;
            }
        }
        return out;
    }

    std::string Trim(const std::string& value)
    {
        size_t first = 0;
        while (first < value.size() && std::isspace(static_cast<unsigned char>(value[first])) != 0)
            ++first;
        size_t last = value.size();
        while (last > first && std::isspace(static_cast<unsigned char>(value[last - 1])) != 0)
            --last;
        return value.substr(first, last - first);
    }

    std::string Lower(std::string value)
    {
        std::transform(value.begin(), value.end(), value.begin(), [](unsigned char c) { return static_cast<char>(std::tolower(c)); });
        return value;
    }

    std::string Initials(const std::string& value)
    {
        std::string out;
        bool take_next = true;
        for (const char c : value)
        {
            if (std::isalnum(static_cast<unsigned char>(c)) == 0)
            {
                take_next = true;
                continue;
            }
            if (take_next || out.empty())
            {
                out.push_back(static_cast<char>(std::toupper(static_cast<unsigned char>(c))));
                take_next = false;
                if (out.size() == 2)
                    break;
            }
        }
        if (out.empty())
            out = "GN";
        return out;
    }

    std::string NowTimeLabel()
    {
        const auto now = std::chrono::system_clock::now();
        const std::time_t tt = std::chrono::system_clock::to_time_t(now);
        std::tm local{};
        localtime_s(&local, &tt);
        std::ostringstream stream;
        stream << std::put_time(&local, "%H:%M");
        return stream.str();
    }

    HttpResponse HttpGet(const std::string& url, const std::string& cookie_header)
    {
        return SendWinHttpRequest(L"GET", url, nullptr, cookie_header);
    }

    HttpResponse HttpPostJson(const std::string& url, const std::string& body, const std::string& cookie_header)
    {
        return SendWinHttpRequest(L"POST", url, &body, cookie_header);
    }

    HttpResponse HttpPostForm(const std::string& url, const std::string& body, const std::string& cookie_header)
    {
        return SendWinHttpRequest(L"POST", url, &body, cookie_header, L"application/x-www-form-urlencoded");
    }

    std::string UrlEncode(const std::string& value)
    {
        std::ostringstream stream;
        stream << std::hex << std::uppercase;
        for (const unsigned char c : value)
        {
            if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') || c == '-' || c == '_' || c == '.' || c == '~')
            {
                stream << static_cast<char>(c);
            }
            else if (c == ' ')
            {
                stream << '+';
            }
            else
            {
                stream << '%' << std::setw(2) << std::setfill('0') << static_cast<int>(c);
            }
        }
        return stream.str();
    }

    std::string CookieHeaderFromSetCookies(const std::vector<std::string>& set_cookies)
    {
        std::vector<std::string> parts;
        for (const std::string& cookie : set_cookies)
        {
            const size_t end = cookie.find(';');
            std::string first = Trim(cookie.substr(0, end));
            if (first.empty() || first.find('=') == std::string::npos)
                continue;
            parts.push_back(first);
        }
        std::ostringstream header;
        for (size_t i = 0; i < parts.size(); ++i)
        {
            if (i > 0)
                header << "; ";
            header << parts[i];
        }
        return header.str();
    }

    bool SaveRememberedCredentials(const std::string& username, const std::string& password)
    {
        std::error_code ec;
        std::filesystem::create_directories(AppDataDirectory(), ec);
        const std::vector<unsigned char> protected_password = ProtectString(password);
        if (protected_password.empty())
            return false;

        std::ofstream file(RememberFile(), std::ios::trunc);
        if (!file)
            return false;
        file << username << "\n" << HexEncode(protected_password) << "\n";
        return true;
    }

    RememberedCredentials LoadRememberedCredentials()
    {
        RememberedCredentials creds;
        std::ifstream file(RememberFile());
        if (!file)
            return creds;
        std::string username;
        std::string encrypted;
        std::getline(file, username);
        std::getline(file, encrypted);
        const std::string password = UnprotectString(HexDecode(Trim(encrypted)));
        if (Trim(username).empty() || password.empty())
            return creds;
        creds.ok = true;
        creds.username = Trim(username);
        creds.password = password;
        return creds;
    }

    void DeleteRememberedCredentials()
    {
        std::error_code ec;
        std::filesystem::remove(RememberFile(), ec);
    }

    bool SaveOddsApiKey(const std::string& key)
    {
        const std::string trimmed = Trim(key);
        if (trimmed.empty())
        {
            DeleteOddsApiKey();
            return true;
        }

        std::error_code ec;
        std::filesystem::create_directories(AppDataDirectory(), ec);
        const std::vector<unsigned char> protected_key = ProtectString(trimmed);
        if (protected_key.empty())
            return false;

        std::ofstream file(OddsApiKeyFile(), std::ios::trunc);
        if (!file)
            return false;
        file << HexEncode(protected_key) << "\n";
        return true;
    }

    std::string LoadOddsApiKey()
    {
        std::ifstream file(OddsApiKeyFile());
        if (!file)
            return {};
        std::string encrypted;
        std::getline(file, encrypted);
        return UnprotectString(HexDecode(Trim(encrypted)));
    }

    void DeleteOddsApiKey()
    {
        std::error_code ec;
        std::filesystem::remove(OddsApiKeyFile(), ec);
    }

    bool SaveKalshiCredentials(const std::string& key_id, const std::string& private_key)
    {
        const std::string trimmed_id = Trim(key_id);
        const std::string trimmed_key = Trim(private_key);
        if (trimmed_id.empty() && trimmed_key.empty())
        {
            DeleteKalshiCredentials();
            return true;
        }

        std::error_code ec;
        std::filesystem::create_directories(AppDataDirectory(), ec);
        const std::string payload = trimmed_id + "\n" + trimmed_key;
        const std::vector<unsigned char> protected_value = ProtectString(payload);
        if (protected_value.empty())
            return false;

        std::ofstream file(KalshiCredentialsFile(), std::ios::trunc);
        if (!file)
            return false;
        file << HexEncode(protected_value) << "\n";
        return true;
    }

    KalshiCredentials LoadKalshiCredentials()
    {
        KalshiCredentials creds;
        std::ifstream file(KalshiCredentialsFile());
        if (!file)
            return creds;
        std::string encrypted;
        std::getline(file, encrypted);
        const std::string payload = UnprotectString(HexDecode(Trim(encrypted)));
        if (payload.empty())
            return creds;

        std::stringstream stream(payload);
        std::getline(stream, creds.key_id);
        std::getline(stream, creds.private_key, '\0');
        creds.key_id = Trim(creds.key_id);
        creds.private_key = Trim(creds.private_key);
        creds.ok = !creds.key_id.empty() || !creds.private_key.empty();
        return creds;
    }

    void DeleteKalshiCredentials()
    {
        std::error_code ec;
        std::filesystem::remove(KalshiCredentialsFile(), ec);
    }

    void AppendDiagnosticLine(const std::string& line)
    {
        std::error_code ec;
        std::filesystem::create_directories(AppDataDirectory(), ec);
        std::ofstream file(AppDataDirectory() / "diagnostics.log", std::ios::app);
        if (!file)
            return;

        file << NowTimeLabel() << " " << line << "\n";
    }

    void OpenExternalUrl(const std::string& url)
    {
        ShellExecuteW(nullptr, L"open", Utf8ToWide(url).c_str(), nullptr, nullptr, SW_SHOWNORMAL);
    }
}
