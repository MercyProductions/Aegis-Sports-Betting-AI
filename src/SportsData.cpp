#include "SportsData.h"

#include "Platform.h"

#include <algorithm>
#include <array>
#include <cmath>
#include <cctype>
#include <chrono>
#include <cstdio>
#include <ctime>
#include <filesystem>
#include <fstream>
#include <iomanip>
#include <limits>
#include <map>
#include <numeric>
#include <optional>
#include <set>
#include <sstream>

namespace aegis
{
    namespace
    {
        std::string ReadString(const JsonValue& object, const std::string& key, const std::string& fallback = "")
        {
            return object[key].AsString(fallback);
        }

        int ReadInt(const JsonValue& object, const std::string& key, int fallback = 0)
        {
            return object[key].AsInt(fallback);
        }

        double ReadDouble(const JsonValue& object, const std::string& key, double fallback = 0.0)
        {
            return object[key].AsDouble(fallback);
        }

        Team ParseTeam(const JsonValue& value)
        {
            Team team;
            team.name = ReadString(value, "name", ReadString(value, "displayName", "Team"));
            team.abbr = ReadString(value, "abbr", ReadString(value, "short", "TM"));
            team.short_name = ReadString(value, "short", team.abbr);
            team.logo = ReadString(value, "logo");
            team.record = ReadString(value, "record");
            team.probability = ReadString(value, "probability");
            team.rating = ReadInt(value, "rating", 0);
            team.score = ReadInt(value, "score", 0);
            team.winner = value["winner"].AsBool(false);
            return team;
        }

        InfoItem ParseInfoItem(const JsonValue& value)
        {
            InfoItem item;
            item.name = ReadString(value, "name", ReadString(value, "title"));
            item.label = ReadString(value, "label");
            item.value = ReadString(value, "value");
            item.weight = ReadString(value, "weight");
            item.detail = ReadString(value, "detail", ReadString(value, "note"));
            item.tag = ReadString(value, "tag");
            item.state = ReadString(value, "state");
            item.book = ReadString(value, "book");
            item.line = ReadString(value, "line");
            item.odds = ReadString(value, "odds");
            item.latency = ReadString(value, "latency");
            item.env = ReadString(value, "env");
            item.status = ReadString(value, "status");
            item.away = ReadString(value, "away");
            item.home = ReadString(value, "home");
            item.edge = ReadString(value, "edge");
            item.time = ReadString(value, "time");
            item.source = ReadString(value, "source");
            return item;
        }

        std::vector<InfoItem> ParseInfoArray(const JsonValue& value)
        {
            std::vector<InfoItem> items;
            if (!value.IsArray())
                return items;
            items.reserve(value.array_value.size());
            for (const JsonValue& row : value.array_value)
                items.push_back(ParseInfoItem(row));
            return items;
        }

        std::vector<float> ParseFloatArray(const JsonValue& value, std::vector<float> fallback)
        {
            if (!value.IsArray())
                return fallback;
            std::vector<float> items;
            for (const JsonValue& item : value.array_value)
                items.push_back(static_cast<float>(item.AsDouble(50.0)));
            return items.empty() ? fallback : items;
        }

        BetLink ParseBetLink(const JsonValue& value)
        {
            BetLink link;
            link.provider_key = ReadString(value, "providerKey", Lower(ReadString(value, "title", "provider")));
            link.title = ReadString(value, "title", "Provider");
            link.kind = ReadString(value, "kind", "Sportsbook");
            link.url = ReadString(value, "url", "#");
            link.market = ReadString(value, "market", "Market");
            link.line = ReadString(value, "line", "Line");
            link.price = ReadString(value, "price", "--");
            link.fair_odds = ReadString(value, "fairOdds");
            link.book_probability = ReadString(value, "bookProbability");
            link.model_edge = ReadString(value, "modelEdge");
            link.source = ReadString(value, "source");
            link.last_update = ReadString(value, "lastUpdate");
            link.movement = ReadString(value, "movement");
            link.note = ReadString(value, "note", "Verify eligibility, location, and final price before taking action.");
            link.available = value["available"].AsBool(false);
            return link;
        }

        TeamComparison ParseTeamComparison(const JsonValue& value)
        {
            TeamComparison comparison;
            if (!value.IsObject())
                return comparison;

            comparison.away = ParseTeam(value["away"]);
            comparison.home = ParseTeam(value["home"]);
            comparison.pick_side = ReadString(value, "pickSide");
            comparison.summary = ReadString(value, "summary");
            comparison.rows = ParseInfoArray(value["rows"]);
            return comparison;
        }

        Game ParseGame(const JsonValue& value)
        {
            Game game;
            game.id = ReadString(value, "id");
            game.league = ReadString(value, "league", "League");
            game.league_key = ReadString(value, "leagueKey");
            game.sport_group = ReadString(value, "sportGroup", "Sports");
            game.matchup = ReadString(value, "matchup");
            game.status_key = Lower(ReadString(value, "statusKey", "scheduled"));
            game.status_label = ReadString(value, "statusLabel", game.status_key == "live" ? "Live" : "Watch");
            game.status_tone = ReadString(value, "statusTone", game.status_key);
            game.clock = ReadString(value, "clock", game.status_label);
            game.detail = ReadString(value, "detail");
            game.feed_age_label = ReadString(value, "feedAgeLabel", "Now");
            game.freshness_state = ReadString(value, "freshnessState", game.feed_age_label == "Fallback" ? "fallback" : "fresh");
            game.source_note = ReadString(value, "sourceNote");
            game.venue = ReadString(value, "venue");
            game.away = ParseTeam(value["away"]);
            game.home = ParseTeam(value["home"]);
            game.spread_favorite = ReadString(value["spread"], "favoriteLine", "--");
            game.spread_other = ReadString(value["spread"], "otherLine", "--");
            game.total_over = ReadString(value["total"], "over", "--");
            game.total_under = ReadString(value["total"], "under", "--");
            game.history = ParseFloatArray(value["history"], { 48, 52, 50, 58, 62, 66, 72, 70 });
            if (game.matchup.empty())
                game.matchup = game.away.abbr + " @ " + game.home.abbr;
            if (value["betLinks"].IsArray())
            {
                for (const JsonValue& link : value["betLinks"].array_value)
                    game.bet_links.push_back(ParseBetLink(link));
            }
            return game;
        }

        Prediction ParsePrediction(const JsonValue& value)
        {
            Prediction prediction;
            prediction.game_id = ReadString(value, "gameId");
            prediction.pick = ReadString(value, "pick", "Pick");
            prediction.predicted_winner = ReadString(value, "predictedWinner", ReadString(value, "winner"));
            prediction.matchup = ReadString(value, "matchup", "Matchup");
            prediction.market = ReadString(value, "market", "Market");
            prediction.league = ReadString(value, "league", "Sports");
            prediction.sport_group = ReadString(value, "sportGroup", "Sports");
            prediction.status_key = ReadString(value, "statusKey", "scheduled");
            prediction.status_label = ReadString(value, "statusLabel", "Watch");
            prediction.confidence_value = ReadInt(value, "confidenceValue", 58);
            prediction.confidence = ReadString(value, "confidence", std::to_string(prediction.confidence_value) + "%");
            prediction.odds = ReadString(value, "odds", ReadString(value, "fairOdds", "-110"));
            prediction.fair_odds = ReadString(value, "fairOdds", prediction.odds);
            prediction.fair_probability = ReadString(value, "fairProbability", prediction.confidence);
            prediction.edge = ReadString(value, "edge", "+0.0%");
            prediction.expected_value = ReadString(value, "expectedValue", "$0.00");
            prediction.risk = ReadString(value, "risk", "Model risk");
            prediction.reason = ReadString(value, "reason");
            prediction.model_version = ReadString(value, "modelVersion");
            prediction.input_count = ReadInt(value, "inputCount", 0);
            prediction.missing_input_penalty = ReadInt(value, "missingInputPenalty", 0);
            prediction.best_book = ReadString(value, "bestBook", "Provider links");
            prediction.book_line = ReadString(value, "bookLine", prediction.pick);
            prediction.can_bet = value.Has("canBet") ? value["canBet"].AsBool(true) : true;

            const JsonValue& breakdown = value["breakdown"];
            if (value["marketLinks"].IsArray())
            {
                for (const JsonValue& link : value["marketLinks"].array_value)
                    prediction.market_links.push_back(ParseBetLink(link));
            }
            prediction.steps = ParseInfoArray(breakdown["steps"]);
            prediction.factors = ParseInfoArray(breakdown["factors"]);
            prediction.missing_inputs = ParseInfoArray(breakdown["missingInputs"]);
            prediction.comparison = ParseTeamComparison(breakdown["comparison"].IsObject() ? breakdown["comparison"] : value["teamComparison"]);
            if (prediction.steps.empty())
            {
                prediction.steps = {
                    {"Start neutral", "", "50%", "", "Every pick begins from a neutral baseline before available signals are added."},
                    {"Game context", "", prediction.status_label, "", "Live, scheduled, and final games are scored differently."},
                    {"Market evidence", "", prediction.market, "", "Spread, total, and line depth change the model posture."},
                    {"Final confidence", "", prediction.confidence, "", "Displayed as an informational estimate, never as a guarantee."}
                };
            }
            if (prediction.factors.empty())
            {
                prediction.factors = {
                    {"Core team strength", "", "Partial", "", "Score, status, records, and available line snapshots are included."},
                    {"Betting market data", "", prediction.edge, "", "Live sportsbook depth improves this signal when connected."},
                    {"Injuries and lineups", "", "Needs setup", "", "Manual verification is still required before acting on any market."}
                };
            }
            if (prediction.missing_inputs.empty())
            {
                prediction.missing_inputs = {
                    {"Injury feed", "", "Manual check", "", "Verify official injuries, scratches, and lineup changes."}
                };
            }
            return prediction;
        }

        std::string SearchHaystack(const Game& game)
        {
            return Lower(game.matchup + " " + game.league + " " + game.league_key + " " + game.sport_group + " " + game.status_key + " " + game.status_label + " " + game.away.name + " " + game.away.abbr + " " + game.home.name + " " + game.home.abbr);
        }

        bool ContainsToken(const std::string& haystack, const std::string& needle)
        {
            return needle.empty() || haystack.find(needle) != std::string::npos;
        }

        bool GameMatchesFilter(const Game& game, const std::string& filter)
        {
            const std::string f = Lower(filter.empty() ? "all" : filter);
            if (f == "all")
                return true;
            if (f == "live" || f == "scheduled" || f == "final" || f == "alert")
                return Lower(game.status_key) == f || Lower(game.status_tone) == f;
            if (f.rfind("league:", 0) == 0)
            {
                const std::string target = f.substr(7);
                return Lower(game.league_key).find(target) != std::string::npos || Lower(game.league).find(target) != std::string::npos || Lower(game.id).find(target) != std::string::npos;
            }
            if (f.rfind("group:", 0) == 0)
            {
                const std::string target = f.substr(6);
                return Lower(game.sport_group).find(target) != std::string::npos || Lower(game.league).find(target) != std::string::npos;
            }
            return SearchHaystack(game).find(f) != std::string::npos;
        }

        std::string FormatMoney(double value)
        {
            char buffer[64]{};
            std::snprintf(buffer, sizeof(buffer), "$%.2f", value);
            return buffer;
        }

        struct ProviderLeague
        {
            std::string key;
            std::string sport;
            std::string league;
            std::string label;
            std::string group;
            int priority = 0;
        };

        struct Bookmaker
        {
            std::string provider_key;
            std::string title;
            std::string kind;
            std::string odds_key;
            std::string url;
            std::string note;
        };

        struct StatusMeta
        {
            std::string key;
            std::string label;
            std::string tone;
            std::string clock;
            std::string detail;
        };

        int ClampInt(int value, int min_value, int max_value)
        {
            return std::max(min_value, std::min(max_value, value));
        }

        double ClampDouble(double value, double min_value, double max_value)
        {
            return std::max(min_value, std::min(max_value, value));
        }

        int EnvInt(const wchar_t* name, int fallback)
        {
            const std::string value = Trim(GetEnvUtf8(name));
            if (value.empty())
                return fallback;
            try
            {
                return std::stoi(value);
            }
            catch (...)
            {
                return fallback;
            }
        }

        int SportsBucket(int refresh_seconds)
        {
            const int cadence = std::max(1, refresh_seconds);
            return static_cast<int>(std::time(nullptr) / cadence);
        }

        struct LineSnapshot
        {
            std::string line;
            std::string price;
            std::string seen_at;
        };

        std::string SnapshotField(std::string value)
        {
            for (char& c : value)
            {
                if (c == '\t' || c == '\r' || c == '\n')
                    c = ' ';
            }
            return Trim(value);
        }

        std::vector<std::string> SplitTabs(const std::string& line)
        {
            std::vector<std::string> parts;
            std::string part;
            std::stringstream stream(line);
            while (std::getline(stream, part, '\t'))
                parts.push_back(part);
            return parts;
        }

        void PruneTsvFile(const std::filesystem::path& path, size_t max_rows)
        {
            std::ifstream file(path);
            if (!file)
                return;

            std::vector<std::string> rows;
            std::string line;
            while (std::getline(file, line))
                rows.push_back(line);
            file.close();

            if (rows.size() <= max_rows)
                return;

            std::ofstream out(path, std::ios::trunc);
            if (!out)
                return;
            const size_t start = rows.size() - max_rows;
            for (size_t i = start; i < rows.size(); ++i)
                out << rows[i] << '\n';
        }

        std::filesystem::path MarketSnapshotFile()
        {
            return AppDataDirectory() / "market-snapshots.tsv";
        }

        std::filesystem::path PredictionAuditFile()
        {
            return AppDataDirectory() / "prediction-audit.tsv";
        }

        std::string SnapshotKey(const Game& game, const BetLink& link)
        {
            return SnapshotField(game.id + "|" + link.provider_key + "|" + link.market);
        }

        std::map<std::string, LineSnapshot> LoadMarketSnapshots()
        {
            std::map<std::string, LineSnapshot> snapshots;
            std::ifstream file(MarketSnapshotFile());
            if (!file)
                return snapshots;

            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabs(line);
                if (parts.size() < 6)
                    continue;
                snapshots[parts[0]] = { parts[3], parts[4], parts[5] };
            }
            return snapshots;
        }

        void AppendMarketSnapshots(const std::vector<Game>& games)
        {
            std::ofstream file(MarketSnapshotFile(), std::ios::app);
            if (!file)
                return;

            const std::string seen_at = NowTimeLabel();
            for (const Game& game : games)
            {
                for (const BetLink& link : game.bet_links)
                {
                    if (!link.available || link.price.empty() || link.price == "--")
                        continue;
                    file << SnapshotKey(game, link) << '\t'
                         << SnapshotField(game.matchup) << '\t'
                         << SnapshotField(link.title) << '\t'
                         << SnapshotField(link.line) << '\t'
                         << SnapshotField(link.price) << '\t'
                         << SnapshotField(seen_at) << '\n';
                }
            }
            file.close();
            PruneTsvFile(MarketSnapshotFile(), 5000);
        }

        std::string MovementFromSnapshot(const LineSnapshot* previous, const BetLink& link)
        {
            if (previous == nullptr)
                return "First tracked";
            if (previous->line == link.line && previous->price == link.price)
                return "No movement";
            if (previous->line != link.line && previous->price != link.price)
                return "Line and price moved";
            if (previous->line != link.line)
                return "Line moved";
            return "Price moved";
        }

        struct AuditSummary
        {
            int samples = 0;
            int graded = 0;
            int wins = 0;
            double confidence_sum = 0.0;
        };

        bool AuditTeamMatch(const std::string& predicted, const Team& team)
        {
            const std::string value = Lower(predicted);
            if (value.empty())
                return false;
            const std::array<std::string, 3> aliases = { Lower(team.name), Lower(team.abbr), Lower(team.short_name) };
            for (const std::string& alias : aliases)
            {
                if (!alias.empty() && value.find(alias) != std::string::npos)
                    return true;
            }
            return false;
        }

        void AppendPredictionAudit(const std::vector<Game>& games, const std::vector<Prediction>& predictions)
        {
            std::map<std::string, const Game*> games_by_id;
            for (const Game& game : games)
                games_by_id[game.id] = &game;

            std::ofstream file(PredictionAuditFile(), std::ios::app);
            if (!file)
                return;

            const std::string seen_at = NowTimeLabel();
            for (const Prediction& prediction : predictions)
            {
                const auto game_it = games_by_id.find(prediction.game_id);
                const Game* game = game_it == games_by_id.end() ? nullptr : game_it->second;
                std::string actual = "open";
                std::string result = "open";
                if (game != nullptr && game->status_key == "final")
                {
                    actual = game->home.winner ? game->home.name : game->away.name;
                    result = AuditTeamMatch(prediction.predicted_winner, game->home) && game->home.winner ? "win" :
                        (AuditTeamMatch(prediction.predicted_winner, game->away) && game->away.winner ? "win" : "loss");
                }

                file << SnapshotField(seen_at) << '\t'
                     << SnapshotField(prediction.game_id) << '\t'
                     << SnapshotField(prediction.matchup) << '\t'
                     << SnapshotField(prediction.market) << '\t'
                     << SnapshotField(prediction.predicted_winner) << '\t'
                     << prediction.confidence_value << '\t'
                     << SnapshotField(actual) << '\t'
                     << SnapshotField(result) << '\n';
            }
            file.close();
            PruneTsvFile(PredictionAuditFile(), 5000);
        }

        AuditSummary ReadAuditSummary()
        {
            AuditSummary summary;
            std::ifstream file(PredictionAuditFile());
            if (!file)
                return summary;

            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabs(line);
                if (parts.size() < 8)
                    continue;
                ++summary.samples;
                try
                {
                    summary.confidence_sum += static_cast<double>(std::stoi(parts[5]));
                }
                catch (...)
                {
                }
                if (parts[7] == "win" || parts[7] == "loss")
                {
                    ++summary.graded;
                    if (parts[7] == "win")
                        ++summary.wins;
                }
            }
            return summary;
        }

        std::string FormatLineValue(double value)
        {
            char buffer[48]{};
            const double rounded = std::round(value);
            if (std::fabs(value - rounded) < 0.01)
                std::snprintf(buffer, sizeof(buffer), "%+.0f", value);
            else
                std::snprintf(buffer, sizeof(buffer), "%+.1f", value);
            return buffer;
        }

        std::string FormatPointValue(double value)
        {
            char buffer[48]{};
            const double rounded = std::round(value);
            if (std::fabs(value - rounded) < 0.01)
                std::snprintf(buffer, sizeof(buffer), "%.0f", value);
            else
                std::snprintf(buffer, sizeof(buffer), "%.1f", value);
            return buffer;
        }

        std::string FormatSignedPercent(double value, int precision = 1)
        {
            char buffer[48]{};
            const char* format = precision == 0 ? "%+.0f%%" : "%+.1f%%";
            std::snprintf(buffer, sizeof(buffer), format, value);
            return buffer;
        }

        std::string FormatSignedPoints(int value)
        {
            return (value >= 0 ? "+" : "") + std::to_string(value) + " pts";
        }

        std::string ProbabilityToAmerican(double probability)
        {
            const double p = ClampDouble(probability, 0.01, 0.99);
            const int odds = p >= 0.5
                ? static_cast<int>(std::round(-100.0 * p / (1.0 - p)))
                : static_cast<int>(std::round(100.0 * (1.0 - p) / p));
            return odds > 0 ? "+" + std::to_string(odds) : std::to_string(odds);
        }

        std::optional<double> AmericanToProbability(const std::string& odds_text)
        {
            std::string cleaned;
            for (const char c : odds_text)
            {
                if ((c == '+' || c == '-') && cleaned.empty())
                    cleaned.push_back(c);
                else if (std::isdigit(static_cast<unsigned char>(c)) != 0)
                    cleaned.push_back(c);
            }
            if (cleaned.empty() || cleaned == "+" || cleaned == "-")
                return std::nullopt;

            const int odds = std::atoi(cleaned.c_str());
            if (odds == 0)
                return std::nullopt;
            if (odds > 0)
                return 100.0 / (static_cast<double>(odds) + 100.0);
            return std::abs(static_cast<double>(odds)) / (std::abs(static_cast<double>(odds)) + 100.0);
        }

        std::string FormatAmericanPrice(const JsonValue& value)
        {
            if (value.IsNull())
                return "--";
            int price = value.AsInt(0);
            if (price == 0)
                return "--";
            return price > 0 ? "+" + std::to_string(price) : std::to_string(price);
        }

        std::string NormalizeName(const std::string& value)
        {
            std::string out;
            bool previous_space = true;
            for (const char c : Lower(value))
            {
                const unsigned char uc = static_cast<unsigned char>(c);
                if (std::isalnum(uc) != 0)
                {
                    out.push_back(static_cast<char>(uc));
                    previous_space = false;
                }
                else if (!previous_space)
                {
                    out.push_back(' ');
                    previous_space = true;
                }
            }
            if (!out.empty() && out.back() == ' ')
                out.pop_back();
            return out;
        }

        std::vector<std::string> TeamAliasesFor(const Team& team)
        {
            std::vector<std::string> aliases;
            for (const std::string& raw : { team.name, team.short_name, team.abbr })
            {
                const std::string normalized = NormalizeName(raw);
                if (!normalized.empty() && std::find(aliases.begin(), aliases.end(), normalized) == aliases.end())
                    aliases.push_back(normalized);
            }
            return aliases;
        }

        bool AliasMatches(const std::string& value, const std::vector<std::string>& aliases)
        {
            const std::string normalized = NormalizeName(value);
            if (normalized.empty())
                return false;
            for (const std::string& alias : aliases)
            {
                if (alias.empty())
                    continue;
                if (normalized == alias || normalized.find(alias) != std::string::npos || alias.find(normalized) != std::string::npos)
                    return true;
            }
            return false;
        }

        std::vector<ProviderLeague> ProviderLeagues()
        {
            return {
                {"nba", "basketball", "nba", "NBA", "Basketball", 100},
                {"wnba", "basketball", "wnba", "WNBA", "Basketball", 91},
                {"ncaab", "basketball", "mens-college-basketball", "NCAAB", "Basketball", 94},
                {"ncaaw", "basketball", "womens-college-basketball", "NCAAW", "Basketball", 82},
                {"nfl", "football", "nfl", "NFL", "Football", 100},
                {"ncaaf", "football", "college-football", "NCAAF", "Football", 95},
                {"ufl", "football", "ufl", "UFL", "Football", 72},
                {"mlb", "baseball", "mlb", "MLB", "Baseball", 100},
                {"college-baseball", "baseball", "college-baseball", "NCAA Baseball", "Baseball", 70},
                {"college-softball", "softball", "college-softball", "NCAA Softball", "Softball", 62},
                {"nhl", "hockey", "nhl", "NHL", "Hockey", 100},
                {"ncaa-hockey", "hockey", "mens-college-hockey", "NCAA Hockey", "Hockey", 62},
                {"epl", "soccer", "eng.1", "Premier League", "Soccer", 98},
                {"laliga", "soccer", "esp.1", "LaLiga", "Soccer", 90},
                {"serie-a", "soccer", "ita.1", "Serie A", "Soccer", 88},
                {"bundesliga", "soccer", "ger.1", "Bundesliga", "Soccer", 88},
                {"ligue-1", "soccer", "fra.1", "Ligue 1", "Soccer", 84},
                {"mls", "soccer", "usa.1", "MLS", "Soccer", 86},
                {"nwsl", "soccer", "usa.nwsl", "NWSL", "Soccer", 68},
                {"liga-mx", "soccer", "mex.1", "Liga MX", "Soccer", 80},
                {"ucl", "soccer", "uefa.champions", "Champions League", "Soccer", 92},
                {"uel", "soccer", "uefa.europa", "Europa League", "Soccer", 78},
                {"ufc", "mma", "ufc", "UFC", "Combat", 92},
                {"boxing", "boxing", "boxing", "Boxing", "Combat", 66},
                {"atp", "tennis", "atp", "ATP Tennis", "Tennis", 82},
                {"wta", "tennis", "wta", "WTA Tennis", "Tennis", 82},
                {"pga", "golf", "pga", "PGA Tour", "Golf", 72},
                {"f1", "racing", "f1", "Formula 1", "Racing", 72},
                {"nascar", "racing", "nascar", "NASCAR", "Racing", 70},
                {"ipl", "cricket", "ipl", "IPL Cricket", "Cricket", 65},
                {"rugby", "rugby", "rugby-union", "Rugby Union", "Rugby", 54},
                {"college-lacrosse", "lacrosse", "college-lacrosse", "NCAA Lacrosse", "Lacrosse", 52},
                {"ncaavb", "volleyball", "womens-college-volleyball", "NCAA Volleyball", "Volleyball", 50},
                {"lol", "esports", "league-of-legends", "League of Legends", "Esports", 42},
                {"valorant", "esports", "valorant", "VALORANT", "Esports", 40},
            };
        }

        std::vector<Bookmaker> BookmakerCatalog()
        {
            return {
                {"fanduel", "FanDuel", "Sportsbook", "fanduel", "https://sportsbook.fanduel.com/", "Availability depends on user location and FanDuel account eligibility."},
                {"draftkings", "DraftKings", "Sportsbook", "draftkings", "https://sportsbook.draftkings.com/", "Availability depends on user location and DraftKings account eligibility."},
                {"betmgm", "BetMGM", "Sportsbook", "betmgm", "https://sports.betmgm.com/", "Availability depends on user location and BetMGM account eligibility."},
                {"caesars", "Caesars", "Sportsbook", "williamhill_us", "https://www.caesars.com/sportsbook-and-casino", "Availability depends on user location and Caesars account eligibility."},
                {"espnbet", "ESPN BET", "Sportsbook", "espnbet", "https://espnbet.com/", "Availability depends on user location and ESPN BET account eligibility."},
                {"fanatics", "Fanatics", "Sportsbook", "fanatics", "https://sportsbook.fanatics.com/", "Availability depends on user location and Fanatics account eligibility."},
                {"betrivers", "BetRivers", "Sportsbook", "betrivers", "https://www.betrivers.com/", "Availability depends on user location and BetRivers account eligibility."},
                {"kalshi", "Kalshi", "Prediction Exchange", "", "https://kalshi.com/markets", "Kalshi markets are event contracts, not sportsbook bets."},
            };
        }

        std::vector<ProviderLeague> ActiveProviderLeagues(int tracked_games, int refresh_seconds)
        {
            std::vector<ProviderLeague> all = ProviderLeagues();
            std::sort(all.begin(), all.end(), [](const ProviderLeague& left, const ProviderLeague& right) {
                if (left.priority != right.priority)
                    return left.priority > right.priority;
                return left.label < right.label;
            });

            const int configured = EnvInt(L"AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH", 0);
            int max_leagues = configured > 0 ? configured : (tracked_games >= 80 ? 24 : (tracked_games >= 35 ? 20 : 14));
            max_leagues = ClampInt(max_leagues, 8, 36);

            std::vector<ProviderLeague> core;
            std::vector<ProviderLeague> rotation;
            for (const ProviderLeague& league : all)
            {
                if (league.priority >= 82)
                    core.push_back(league);
                else
                    rotation.push_back(league);
            }

            std::vector<ProviderLeague> selected;
            for (const ProviderLeague& league : core)
            {
                if (static_cast<int>(selected.size()) >= max_leagues)
                    break;
                selected.push_back(league);
            }

            if (static_cast<int>(selected.size()) < max_leagues && !rotation.empty())
            {
                const int offset = SportsBucket(refresh_seconds) % static_cast<int>(rotation.size());
                for (int i = 0; static_cast<int>(selected.size()) < max_leagues && i < static_cast<int>(rotation.size()); ++i)
                    selected.push_back(rotation[static_cast<size_t>((offset + i) % static_cast<int>(rotation.size()))]);
            }

            return selected;
        }

        std::string YmdFromOffset(int days)
        {
            const std::time_t base = std::time(nullptr) + static_cast<std::time_t>(days) * 86400;
            std::tm utc{};
            gmtime_s(&utc, &base);
            char buffer[16]{};
            std::strftime(buffer, sizeof(buffer), "%Y%m%d", &utc);
            return buffer;
        }

        std::string ScoreboardDatesWindow()
        {
            const int backfill_days = ClampInt(EnvInt(L"AEGIS_SPORTS_SCOREBOARD_BACKFILL_DAYS", 1), 0, 7);
            const int lookahead_days = ClampInt(EnvInt(L"AEGIS_SPORTS_SCOREBOARD_LOOKAHEAD_DAYS", 4), 1, 21);
            return YmdFromOffset(-backfill_days) + "-" + YmdFromOffset(lookahead_days);
        }

        std::string ProviderUrl(const ProviderLeague& league, const std::string& dates)
        {
            std::string url = "https://site.api.espn.com/apis/site/v2/sports/" + UrlEncode(league.sport) + "/" + UrlEncode(league.league) + "/scoreboard?limit=100";
            if (!dates.empty())
                url += "&dates=" + UrlEncode(dates);
            return url;
        }

        std::vector<float> Resample(const std::vector<float>& values, int count, int min_value = 18, int max_value = 96)
        {
            if (count <= 0)
                return {};
            if (values.empty())
                return std::vector<float>(static_cast<size_t>(count), static_cast<float>((min_value + max_value) / 2));
            if (values.size() == 1)
                return std::vector<float>(static_cast<size_t>(count), std::clamp(values.front(), static_cast<float>(min_value), static_cast<float>(max_value)));

            const auto [source_min_it, source_max_it] = std::minmax_element(values.begin(), values.end());
            const float source_min = *source_min_it;
            const float source_max = *source_max_it;
            const float source_range = std::max(1.0f, source_max - source_min);
            std::vector<float> out;
            out.reserve(static_cast<size_t>(count));
            for (int i = 0; i < count; ++i)
            {
                const float position = count == 1 ? 0.0f : (static_cast<float>(i) / static_cast<float>(count - 1)) * static_cast<float>(values.size() - 1);
                const int left = static_cast<int>(std::floor(position));
                const int right = std::min(static_cast<int>(values.size()) - 1, left + 1);
                const float t = position - static_cast<float>(left);
                const float interpolated = values[static_cast<size_t>(left)] * (1.0f - t) + values[static_cast<size_t>(right)] * t;
                const float normalized = (interpolated - source_min) / source_range;
                out.push_back(static_cast<float>(min_value) + normalized * static_cast<float>(max_value - min_value));
            }
            return out;
        }

        Team ParseProviderTeam(const JsonValue& competitors, const std::string& home_away)
        {
            const JsonValue* selected = nullptr;
            if (competitors.IsArray())
            {
                for (const JsonValue& competitor : competitors.array_value)
                {
                    if (ReadString(competitor, "homeAway") == home_away)
                    {
                        selected = &competitor;
                        break;
                    }
                }
                if (selected == nullptr && !competitors.array_value.empty())
                {
                    const size_t fallback_index = home_away == "away" ? 0u : std::min<size_t>(1u, competitors.array_value.size() - 1u);
                    selected = &competitors.array_value[fallback_index];
                }
            }

            Team out;
            out.name = home_away == "away" ? "Away" : "Home";
            out.abbr = home_away == "away" ? "AWY" : "HME";
            out.short_name = out.abbr;
            if (selected == nullptr)
                return out;

            const JsonValue& team = (*selected)["team"];
            const JsonValue& athlete = (*selected)["athlete"];
            out.name = ReadString(team, "displayName",
                ReadString(team, "shortDisplayName",
                    ReadString(team, "name",
                        ReadString(athlete, "displayName",
                            ReadString(*selected, "displayName", out.name)))));
            out.abbr = ReadString(team, "abbreviation", out.abbr);
            if (out.abbr.empty())
            {
                std::string compact;
                for (const char c : out.name)
                {
                    if (std::isalnum(static_cast<unsigned char>(c)) != 0)
                        compact.push_back(static_cast<char>(std::toupper(static_cast<unsigned char>(c))));
                    if (compact.size() == 3)
                        break;
                }
                out.abbr = compact.empty() ? (home_away == "away" ? "AWY" : "HME") : compact;
            }
            out.short_name = ReadString(team, "shortDisplayName", out.abbr);
            out.logo = ReadString(team, "logo");
            out.score = ReadInt(*selected, "score", 0);
            out.winner = (*selected)["winner"].AsBool(false);

            const JsonValue& records = (*selected)["records"];
            if (records.IsArray())
            {
                for (const JsonValue& record : records.array_value)
                {
                    out.record = ReadString(record, "summary");
                    if (!out.record.empty())
                        break;
                }
            }
            return out;
        }

        StatusMeta ParseStatusMeta(const JsonValue& status, const std::string& fallback_start)
        {
            const JsonValue& type = status["type"];
            const std::string name = Lower(ReadString(type, "name"));
            const std::string state = Lower(ReadString(type, "state"));
            const std::string detail = Trim(ReadString(type, "detail"));
            const std::string short_detail = Trim(ReadString(type, "shortDetail", detail));
            const std::string description = Trim(ReadString(type, "description"));
            const std::string lowered_detail = Lower(detail);

            if (name.find("postponed") != std::string::npos ||
                name.find("delayed") != std::string::npos ||
                lowered_detail.find("postponed") != std::string::npos ||
                lowered_detail.find("delayed") != std::string::npos ||
                lowered_detail.find("suspended") != std::string::npos)
            {
                return { "alert", description.empty() ? "Alert" : description, "alert", short_detail.empty() ? "Status update" : short_detail, detail.empty() ? "Provider flagged this event for follow-up." : detail };
            }

            if (type["completed"].AsBool(false) || state == "post")
                return { "final", "Final", "final", short_detail.empty() ? "Final" : short_detail, detail.empty() ? "Game complete." : detail };

            if (state == "in" || name.find("in_progress") != std::string::npos || name.find("live") != std::string::npos)
                return { "live", "Live", "live", short_detail.empty() ? "Live" : short_detail, detail.empty() ? "In progress." : detail };

            const std::string clock = short_detail.empty() ? (fallback_start.empty() ? "Upcoming" : fallback_start) : short_detail;
            return { "scheduled", "Upcoming", "scheduled", clock, detail.empty() ? "Scheduled to start soon." : detail };
        }

        std::optional<double> FindSignedNumber(const std::string& text)
        {
            for (size_t i = 0; i < text.size(); ++i)
            {
                if ((text[i] == '+' || text[i] == '-') && i + 1 < text.size() && (std::isdigit(static_cast<unsigned char>(text[i + 1])) != 0))
                {
                    try
                    {
                        return std::stod(text.substr(i));
                    }
                    catch (...)
                    {
                        return std::nullopt;
                    }
                }
            }
            return std::nullopt;
        }

        void ApplySpread(Game& game, const std::string& details)
        {
            const std::string text = Trim(details);
            if (text.empty())
            {
                game.spread_favorite = "--";
                game.spread_other = "--";
                return;
            }

            game.spread_favorite = text;
            const std::optional<double> line = FindSignedNumber(text);
            game.spread_other = line.has_value() ? FormatLineValue(-*line) : "--";
        }

        void ApplyTotal(Game& game, const JsonValue& odds)
        {
            if (odds["overUnder"].IsNull())
            {
                game.total_over = "--";
                game.total_under = "--";
                return;
            }

            const double total = odds["overUnder"].AsDouble(-1.0);
            if (total <= 0.0)
            {
                game.total_over = "--";
                game.total_under = "--";
                return;
            }
            const std::string formatted = FormatPointValue(total);
            game.total_over = "O " + formatted;
            game.total_under = "U " + formatted;
        }

        std::vector<float> HistoryFromGame(const JsonValue& competitors, const Team& away, const Team& home)
        {
            std::vector<float> away_values;
            std::vector<float> home_values;
            if (competitors.IsArray())
            {
                for (const JsonValue& competitor : competitors.array_value)
                {
                    std::vector<float>* target = ReadString(competitor, "homeAway") == "home" ? &home_values : &away_values;
                    const JsonValue& linescores = competitor["linescores"];
                    if (linescores.IsArray())
                    {
                        for (const JsonValue& line : linescores.array_value)
                            target->push_back(static_cast<float>(line["value"].AsDouble(line["displayValue"].AsDouble(0.0))));
                    }
                }
            }

            const size_t length = std::max(away_values.size(), home_values.size());
            if (length == 0)
            {
                const float total = static_cast<float>(std::max(1, away.score + home.score));
                return Resample({ total * 0.25f, total * 0.45f, total * 0.70f, total }, 8, 40, 82);
            }

            std::vector<float> totals;
            totals.reserve(length);
            float away_running = 0.0f;
            float home_running = 0.0f;
            for (size_t i = 0; i < length; ++i)
            {
                if (i < away_values.size())
                    away_running += away_values[i];
                if (i < home_values.size())
                    home_running += home_values[i];
                totals.push_back(away_running + home_running);
            }
            return Resample(totals, 8, 32, 94);
        }

        std::optional<Game> ParseProviderGame(const JsonValue& event, const ProviderLeague& league, const std::string& fetched_label)
        {
            const JsonValue& competitions = event["competitions"];
            if (!competitions.IsArray() || competitions.array_value.empty())
                return std::nullopt;

            const JsonValue& competition = competitions.At(0);
            const JsonValue& competitors = competition["competitors"];
            if (!competitors.IsArray() || competitors.array_value.empty())
                return std::nullopt;

            Game game;
            game.id = ReadString(event, "id", ReadString(competition, "id", league.key + "-" + std::to_string(std::time(nullptr))));
            game.league = league.label;
            game.league_key = league.key;
            game.sport_group = league.group;
            game.away = ParseProviderTeam(competitors, "away");
            game.home = ParseProviderTeam(competitors, "home");
            game.matchup = game.away.abbr + " @ " + game.home.abbr;
            const std::string start = ReadString(competition, "date", ReadString(event, "date"));
            const StatusMeta meta = ParseStatusMeta(competition["status"], start);
            game.status_key = meta.key;
            game.status_label = meta.label;
            game.status_tone = meta.tone;
            game.clock = meta.clock;
            game.detail = meta.detail;
            game.feed_age_label = fetched_label;
            game.venue = ReadString(competition["venue"], "fullName");
            const JsonValue& odds_array = competition["odds"];
            const JsonValue& odds = odds_array.IsArray() && !odds_array.array_value.empty() ? odds_array.At(0) : odds_array;
            ApplySpread(game, ReadString(odds, "details"));
            ApplyTotal(game, odds);
            game.history = HistoryFromGame(competitors, game.away, game.home);
            return game;
        }

        int GamePriority(const Game& game)
        {
            const std::string key = Lower(game.status_key);
            if (key == "live")
                return 0;
            if (key == "scheduled")
                return 1;
            if (key == "final")
                return 2;
            return 3;
        }

        std::vector<Game> BuildFallbackGames()
        {
            SportsState demo = MakeDemoSportsState();
            for (Game& game : demo.games)
            {
                if (game.status_key == "live")
                {
                    game.status_key = "scheduled";
                    game.status_label = "Fallback";
                    game.status_tone = "scheduled";
                    game.clock = "Provider unavailable";
                    game.detail = "Fallback board: direct provider feed did not return a usable slate.";
                }
                game.feed_age_label = "Fallback";
                game.freshness_state = "fallback";
                game.source_note = "The app could not build a live provider slate on this refresh.";
            }
            return demo.games;
        }

        std::vector<Game> FetchNativeGames(int tracked_games, int refresh_seconds)
        {
            const std::string dates = ScoreboardDatesWindow();
            const std::vector<ProviderLeague> leagues = ActiveProviderLeagues(tracked_games, refresh_seconds);
            const int budget_seconds = ClampInt(EnvInt(L"AEGIS_SPORTS_FETCH_TIME_BUDGET_SECONDS", 8), 3, 30);
            const auto started = std::chrono::steady_clock::now();
            const std::string fetched_label = "Fresh " + NowTimeLabel();
            std::vector<Game> games;

            for (const ProviderLeague& league : leagues)
            {
                const auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(std::chrono::steady_clock::now() - started).count();
                if (elapsed >= budget_seconds)
                    break;

                const HttpResponse response = HttpGet(ProviderUrl(league, dates));
                if (!response.error.empty() || response.status_code < 200 || response.status_code >= 300)
                    continue;

                const JsonParseResult parsed = ParseJson(response.body);
                if (!parsed.ok)
                    continue;

                const JsonValue& events = parsed.value["events"];
                if (!events.IsArray())
                    continue;

                for (const JsonValue& event : events.array_value)
                {
                    std::optional<Game> game = ParseProviderGame(event, league, fetched_label);
                    if (game.has_value())
                    {
                        game->freshness_state = "fresh";
                        game->source_note = "Direct ESPN public scoreboard refresh.";
                        games.push_back(*game);
                    }
                }
            }

            if (games.empty())
                return BuildFallbackGames();

            std::sort(games.begin(), games.end(), [](const Game& left, const Game& right) {
                const int priority = GamePriority(left) - GamePriority(right);
                if (priority != 0)
                    return priority < 0;
                return left.id < right.id;
            });

            const int max_games = std::max(60, std::min(160, tracked_games + 60));
            if (static_cast<int>(games.size()) > max_games)
                games.resize(static_cast<size_t>(max_games));
            return games;
        }

        std::optional<std::pair<int, int>> RecordParts(const std::string& record)
        {
            std::string first;
            std::string second;
            bool after_dash = false;
            for (const char c : record)
            {
                if (std::isdigit(static_cast<unsigned char>(c)) != 0)
                {
                    (after_dash ? second : first).push_back(c);
                }
                else if (!first.empty() && (c == '-' || c == '/'))
                {
                    after_dash = true;
                }
                else if (after_dash && !second.empty())
                {
                    break;
                }
            }
            if (first.empty() || second.empty())
                return std::nullopt;
            return std::make_pair(std::atoi(first.c_str()), std::atoi(second.c_str()));
        }

        double RecordPct(const Team& team)
        {
            const std::optional<std::pair<int, int>> parts = RecordParts(team.record);
            if (!parts.has_value())
                return 0.5;
            const int games = parts->first + parts->second;
            return games > 0 ? static_cast<double>(parts->first) / static_cast<double>(games) : 0.5;
        }

        int TeamRating(const Team& team, bool home, const Game& game)
        {
            int rating = 50 + static_cast<int>(std::round((RecordPct(team) - 0.5) * 42.0));
            if (home)
                rating += 3;
            if (game.status_key == "live" || game.status_key == "final")
            {
                const int differential = home ? game.home.score - game.away.score : game.away.score - game.home.score;
                rating += ClampInt(differential * 2, -12, 12);
            }
            if (team.winner)
                rating += 6;
            return ClampInt(rating, 18, 96);
        }

        std::string PickSideFromPrediction(const Game& game, const Prediction& prediction)
        {
            const std::string pick = NormalizeName(prediction.pick);
            const std::vector<std::string> away_aliases = TeamAliasesFor(game.away);
            const std::vector<std::string> home_aliases = TeamAliasesFor(game.home);
            for (const std::string& alias : away_aliases)
            {
                if (!alias.empty() && pick.find(alias) != std::string::npos)
                    return "away";
            }
            for (const std::string& alias : home_aliases)
            {
                if (!alias.empty() && pick.find(alias) != std::string::npos)
                    return "home";
            }
            return "";
        }

        TeamComparison BuildTeamComparison(const Game& game, const Prediction& prediction)
        {
            TeamComparison comparison;
            comparison.away = game.away;
            comparison.home = game.home;
            comparison.away.rating = TeamRating(game.away, false, game);
            comparison.home.rating = TeamRating(game.home, true, game);
            comparison.away.probability = std::to_string(ClampInt(comparison.away.rating, 1, 99)) + "%";
            comparison.home.probability = std::to_string(ClampInt(comparison.home.rating, 1, 99)) + "%";
            comparison.pick_side = PickSideFromPrediction(game, prediction);
            comparison.summary = "Direct scoreboard status, record proxy, score context, and available market snapshots are blended conservatively.";

            comparison.rows.push_back({ "Scoreboard state", "", game.status_label, "", game.detail });
            comparison.rows.push_back({ "Away rating", "", std::to_string(comparison.away.rating), "", game.away.record.empty() ? game.away.name : game.away.name + " " + game.away.record });
            comparison.rows.push_back({ "Home rating", "", std::to_string(comparison.home.rating), "", game.home.record.empty() ? game.home.name : game.home.name + " " + game.home.record });
            comparison.rows.push_back({ "Market snapshot", "", prediction.market, "", prediction.book_line.empty() ? prediction.pick : prediction.book_line });
            return comparison;
        }

        std::string PredictedWinnerFromPick(const Game& game, const std::string& pick)
        {
            if (AliasMatches(pick, TeamAliasesFor(game.away)))
                return game.away.name;
            if (AliasMatches(pick, TeamAliasesFor(game.home)))
                return game.home.name;
            if (Lower(pick).find("over") != std::string::npos)
                return "Over";
            if (Lower(pick).find("under") != std::string::npos)
                return "Under";
            return pick;
        }

        Prediction BuildPredictionForGame(const Game& game, int index, int bucket)
        {
            (void)index;
            (void)bucket;
            const bool has_spread = game.spread_favorite != "--";
            const bool has_total = game.total_over != "--";
            const bool has_records = !game.away.record.empty() && !game.home.record.empty();
            const int away_rating = TeamRating(game.away, false, game);
            const int home_rating = TeamRating(game.home, true, game);
            const bool home_stronger = home_rating >= away_rating;

            Prediction prediction;
            prediction.game_id = game.id;
            prediction.matchup = game.matchup;
            prediction.league = game.league;
            prediction.sport_group = game.sport_group;
            prediction.status_key = game.status_key;
            prediction.status_label = game.status_label;
            prediction.model_version = "Aegis Source Model v2";
            prediction.can_bet = game.status_key != "final";
            prediction.risk = game.status_key == "live" ? "Live volatility" : (game.status_key == "final" ? "Closed market" : "Pregame risk");

            if (game.status_key == "final")
            {
                const std::string winner = game.home.winner ? game.home.name : game.away.name;
                prediction.pick = "Postgame review: " + winner;
                prediction.market = "Audit";
                prediction.reason = "This event is final on the live scoreboard. Keep it for grading and model review rather than new action.";
            }
            else if (has_spread)
            {
                prediction.pick = game.spread_favorite;
                prediction.market = "Spread";
                prediction.reason = game.status_key == "live"
                    ? "Live scoreboard state and the current line snapshot are aligned, so this is the cleanest market to watch right now."
                    : "Scheduled board shows a valid spread snapshot, making this the strongest pregame lane to monitor.";
            }
            else if (has_total)
            {
                prediction.pick = game.total_over;
                prediction.market = "Total";
                prediction.reason = game.status_key == "live"
                    ? "The feed has a live total but limited book depth, so treat this as an informational edge watch."
                    : "Pregame total is available on the feed, so this is the clearest market to stage for review.";
            }
            else
            {
                const Team& preferred = home_stronger ? game.home : game.away;
                prediction.pick = preferred.name + " moneyline watch";
                prediction.market = "Moneyline Watch";
                prediction.reason = "No direct market line is present yet, so Aegis uses team strength and scoreboard context as a watchlist signal.";
            }

            prediction.predicted_winner = PredictedWinnerFromPick(game, prediction.pick);
            prediction.book_line = prediction.pick;
            prediction.best_book = "Provider links";
            const std::string side = PickSideFromPrediction(game, prediction);
            int side_rating_edge = 0;
            int live_score_alignment = 0;
            if (side == "home")
            {
                side_rating_edge = home_rating - away_rating;
                if (game.status_key == "live" || game.status_key == "final")
                    live_score_alignment = ClampInt((game.home.score - game.away.score) * 2, -14, 14);
            }
            else if (side == "away")
            {
                side_rating_edge = away_rating - home_rating;
                if (game.status_key == "live" || game.status_key == "final")
                    live_score_alignment = ClampInt((game.away.score - game.home.score) * 2, -14, 14);
            }

            const bool side_pick = side == "home" || side == "away";
            const int status_score = game.status_key == "live" ? 5 : (game.status_key == "scheduled" ? 2 : (game.status_key == "final" ? -8 : -2));
            const int market_score = (has_spread ? 8 : 0) + (has_total ? 3 : 0);
            const int record_score = has_records ? 3 : -3;
            const int rating_score = side_pick
                ? ClampInt(static_cast<int>(std::round(side_rating_edge * 0.42)), -10, 13)
                : ClampInt(static_cast<int>(std::round(std::abs(home_rating - away_rating) * 0.22)), 0, 7);
            prediction.missing_input_penalty = 6 + (has_records ? 0 : 5) + ((has_spread || has_total) ? 0 : 4);
            prediction.input_count = 2 + (has_records ? 1 : 0) + ((has_spread || has_total) ? 1 : 0) + ((game.status_key == "live" || game.status_key == "final") ? 1 : 0);

            int raw_confidence = 50 + rating_score + status_score + market_score + record_score + live_score_alignment - prediction.missing_input_penalty;
            if (!side_pick && prediction.market == "Total")
                raw_confidence = 54 + status_score + market_score + (has_records ? 2 : -2) - (prediction.missing_input_penalty / 2);

            int cap = (has_spread || has_total) ? 82 : 74;
            if (!has_records)
                cap = std::min(cap, 70);
            if (game.status_key == "final")
                cap = 61;
            if (game.status_key == "alert")
                cap = 58;
            const int confidence = ClampInt(raw_confidence, game.status_key == "final" ? 50 : 52, cap);

            prediction.confidence_value = confidence;
            prediction.confidence = std::to_string(confidence) + "%";
            prediction.fair_probability = FormatPointValue(static_cast<double>(confidence)) + "%";
            prediction.fair_odds = ProbabilityToAmerican(static_cast<double>(confidence) / 100.0);
            prediction.edge = game.status_key == "final" ? "+0.0%" : FormatSignedPercent(ClampDouble(confidence - 50.0, 1.5, 16.5));
            prediction.expected_value = game.status_key == "final" ? FormatMoney(0.0) : FormatMoney(std::max(0.0, (confidence - 50.0) * 1.85));
            prediction.odds = (has_spread || has_total) && game.status_key != "final" ? "Feed snapshot" : "--";
            prediction.steps = {
                {"1. Start neutral", "", "50%", "", "Every pick begins from a neutral baseline before available source signals are added."},
                {"2. Rate both teams", "", FormatSignedPoints(side_pick ? side_rating_edge : std::abs(home_rating - away_rating)), "", "Record proxy, home field, winner flag, and live score context are blended into team ratings."},
                {"3. Add scoreboard state", "", prediction.status_label, "", game.feed_age_label.empty() ? "Provider status is included when available." : game.feed_age_label},
                {"4. Add market evidence", "", prediction.market, "", (has_spread || has_total) ? "Spread or total context is active on this matchup." : "No direct line yet, so this remains a watchlist signal."},
                {"5. Apply missing-data penalty", "", "-" + std::to_string(prediction.missing_input_penalty) + " pts", "", "Unavailable injuries, lineups, and premium tracking keep confidence conservative."},
                {"6. Final confidence", "", prediction.confidence, "", "Displayed as an informational estimate, never as a guarantee."}
            };
            prediction.factors = {
                {"Team rating edge", "", FormatSignedPoints(side_pick ? side_rating_edge : std::abs(home_rating - away_rating)), "", "Ratings use records, home field, final/winner flags, and live score context."},
                {"Live score alignment", "", FormatSignedPoints(live_score_alignment), "", "Only active during live or completed games, and only when the pick maps to a side."},
                {"Market evidence", "", prediction.edge, "", "Direct sportsbook prices improve edge calculations when an odds key is configured."},
                {"Lineups and injuries", "", "Manual check", "", "Official reports and late scratches must still be verified."}
            };
            prediction.missing_inputs = {
                {"Injury feed", "", "Manual check", "", "Connect a verified injury or lineup source before treating availability as confirmed."},
                {"Lineup feed", "", "Manual check", "", "Starting lineup and scratch feeds are not yet connected."},
                {"Execution mode", "", "Informational", "", "Aegis opens market links for review; it does not place real bets."}
            };
            prediction.comparison = BuildTeamComparison(game, prediction);
            return prediction;
        }

        std::vector<Prediction> BuildNativePredictions(const std::vector<Game>& games, int model_count, int bucket)
        {
            std::vector<const Game*> preferred;
            for (const Game& game : games)
            {
                if (game.status_key != "final")
                    preferred.push_back(&game);
            }
            if (preferred.empty())
            {
                for (const Game& game : games)
                    preferred.push_back(&game);
            }

            const int limit = std::max(1, std::min(static_cast<int>(preferred.size()), std::max(5, model_count + 3)));
            std::vector<Prediction> predictions;
            predictions.reserve(static_cast<size_t>(limit));
            for (int i = 0; i < limit; ++i)
                predictions.push_back(BuildPredictionForGame(*preferred[static_cast<size_t>(i)], i, bucket));
            return predictions;
        }

        std::string ConfiguredOddsApiKey(const std::string& configured_key)
        {
            const std::string configured = Trim(configured_key);
            if (!configured.empty())
                return configured;
            for (const wchar_t* name : { L"AEGIS_ODDS_API_KEY", L"ODDS_API_KEY", L"THE_ODDS_API_KEY" })
            {
                const std::string value = Trim(GetEnvUtf8(name));
                if (!value.empty())
                    return value;
            }
            return {};
        }

        struct MarketAccessSummary
        {
            int available_lines = 0;
            int matched_events = 0;
            bool odds_configured = false;
            bool odds_reachable = false;
            int bookmakers = 0;
            int odds_calls = 0;
            int odds_errors = 0;
            std::string odds_status = "Needs API key";
            std::string odds_detail = "Add an Odds API key in Settings to enable direct sportsbook line matching.";
        };

        std::string HeaderValue(const std::string& raw_headers, const std::string& header_name)
        {
            const std::string target = Lower(header_name) + ":";
            std::stringstream stream(raw_headers);
            std::string line;
            while (std::getline(stream, line))
            {
                if (!line.empty() && line.back() == '\r')
                    line.pop_back();
                const std::string lowered = Lower(line);
                if (lowered.rfind(target, 0) == 0)
                    return Trim(line.substr(target.size()));
            }
            return {};
        }

        std::string OddsBookmakers()
        {
            std::vector<std::string> keys;
            for (const Bookmaker& book : BookmakerCatalog())
            {
                if (!book.odds_key.empty())
                    keys.push_back(book.odds_key);
            }
            std::ostringstream stream;
            for (size_t i = 0; i < keys.size(); ++i)
            {
                if (i > 0)
                    stream << ',';
                stream << keys[i];
            }
            return stream.str();
        }

        std::string OddsSportKey(const Game& game)
        {
            static const std::map<std::string, std::string> map = {
                {"nba", "basketball_nba"},
                {"wnba", "basketball_wnba"},
                {"ncaab", "basketball_ncaab"},
                {"ncaaw", "basketball_ncaab"},
                {"nfl", "americanfootball_nfl"},
                {"ncaaf", "americanfootball_ncaaf"},
                {"mlb", "baseball_mlb"},
                {"nhl", "icehockey_nhl"},
                {"epl", "soccer_epl"},
                {"laliga", "soccer_spain_la_liga"},
                {"serie-a", "soccer_italy_serie_a"},
                {"bundesliga", "soccer_germany_bundesliga"},
                {"ligue-1", "soccer_france_ligue_one"},
                {"mls", "soccer_usa_mls"},
                {"liga-mx", "soccer_mexico_ligamx"},
                {"ucl", "soccer_uefa_champs_league"},
                {"uel", "soccer_uefa_europa_league"},
                {"ufc", "mma_mixed_martial_arts"},
                {"atp", "tennis_atp"},
                {"wta", "tennis_wta"},
                {"pga", "golf_pga_championship_winner"},
            };
            const auto exact = map.find(Lower(game.league_key));
            if (exact != map.end())
                return exact->second;
            const std::string league = Lower(game.league);
            for (const auto& [needle, sport_key] : map)
            {
                if (league.find(needle) != std::string::npos)
                    return sport_key;
            }
            return {};
        }

        std::vector<JsonValue> FetchOddsEvents(const std::string& sport_key, const std::string& api_key, MarketAccessSummary& summary)
        {
            if (sport_key.empty() || api_key.empty())
                return {};
            const std::string url = "https://api.the-odds-api.com/v4/sports/" + UrlEncode(sport_key) +
                "/odds/?apiKey=" + UrlEncode(api_key) +
                "&regions=us&markets=h2h,spreads,totals&oddsFormat=american&dateFormat=iso&bookmakers=" + UrlEncode(OddsBookmakers());
            const HttpResponse response = HttpGet(url);
            ++summary.odds_calls;
            if (!response.error.empty() || response.status_code < 200 || response.status_code >= 300)
            {
                ++summary.odds_errors;
                summary.odds_reachable = false;
                if (!response.error.empty())
                    summary.odds_detail = response.error;
                else if (response.status_code == 401 || response.status_code == 403)
                    summary.odds_detail = "Odds API rejected the key. Check the key in Settings.";
                else if (response.status_code == 429)
                    summary.odds_detail = "Odds API rate limit reached. The app will keep scoreboard data live.";
                else
                    summary.odds_detail = "Odds API returned HTTP " + std::to_string(response.status_code) + ".";
                return {};
            }
            summary.odds_reachable = true;
            summary.odds_status = "Connected";
            summary.odds_detail = "Direct sportsbook odds returned from The Odds API.";
            const JsonParseResult parsed = ParseJson(response.body);
            if (!parsed.ok || !parsed.value.IsArray())
            {
                ++summary.odds_errors;
                summary.odds_detail = "Odds API response could not be parsed.";
                return {};
            }
            return parsed.value.array_value;
        }

        std::optional<JsonValue> FindMatchingOddsEvent(const Game& game, const std::vector<JsonValue>& events)
        {
            const std::vector<std::string> home_aliases = TeamAliasesFor(game.home);
            const std::vector<std::string> away_aliases = TeamAliasesFor(game.away);
            for (const JsonValue& event : events)
            {
                const std::string event_home = ReadString(event, "home_team");
                const std::string event_away = ReadString(event, "away_team");
                const bool direct = AliasMatches(event_home, home_aliases) && AliasMatches(event_away, away_aliases);
                const bool swapped = AliasMatches(event_home, away_aliases) && AliasMatches(event_away, home_aliases);
                if (direct || swapped)
                    return event;
            }
            return std::nullopt;
        }

        std::optional<JsonValue> SelectOutcome(const JsonValue& outcomes, const std::string& market_key, const Game& game, const Prediction& prediction)
        {
            if (!outcomes.IsArray() || outcomes.array_value.empty())
                return std::nullopt;

            const std::string pick = Lower(prediction.pick);
            if (market_key == "totals")
            {
                const bool prefer_under = pick.find("under") != std::string::npos || pick.find("u ") != std::string::npos;
                const bool prefer_over = !prefer_under;
                for (const JsonValue& outcome : outcomes.array_value)
                {
                    const std::string name = Lower(ReadString(outcome, "name"));
                    if ((prefer_over && name.find("over") != std::string::npos) || (prefer_under && name.find("under") != std::string::npos))
                        return outcome;
                }
            }

            const std::string side = PickSideFromPrediction(game, prediction);
            if (!side.empty())
            {
                const std::vector<std::string> aliases = side == "away" ? TeamAliasesFor(game.away) : TeamAliasesFor(game.home);
                for (const JsonValue& outcome : outcomes.array_value)
                {
                    if (AliasMatches(ReadString(outcome, "name"), aliases))
                        return outcome;
                }
            }

            return outcomes.array_value.front();
        }

        std::optional<BetLink> ExtractBookMarket(const JsonValue& bookmaker, const Bookmaker& catalog, const Game& game, const Prediction& prediction)
        {
            const std::string market_label = Lower(prediction.market);
            std::vector<std::string> preferred = market_label.find("total") != std::string::npos
                ? std::vector<std::string>{ "totals", "spreads", "h2h" }
                : (market_label.find("spread") != std::string::npos ? std::vector<std::string>{ "spreads", "h2h", "totals" } : std::vector<std::string>{ "h2h", "spreads", "totals" });
            const std::map<std::string, std::string> titles = { {"h2h", "Moneyline"}, {"spreads", "Spread"}, {"totals", "Total"} };

            const JsonValue& markets = bookmaker["markets"];
            if (!markets.IsArray())
                return std::nullopt;

            for (const std::string& preferred_key : preferred)
            {
                for (const JsonValue& market : markets.array_value)
                {
                    if (ReadString(market, "key") != preferred_key)
                        continue;

                    std::optional<JsonValue> outcome = SelectOutcome(market["outcomes"], preferred_key, game, prediction);
                    if (!outcome.has_value())
                        continue;

                    BetLink link;
                    link.provider_key = catalog.provider_key;
                    link.title = catalog.title;
                    link.kind = catalog.kind;
                    link.url = catalog.url;
                    link.market = titles.at(preferred_key);
                    link.price = FormatAmericanPrice((*outcome)["price"]);
                    link.source = "The Odds API";
                    link.last_update = ReadString(market, "last_update", ReadString(bookmaker, "last_update"));
                    link.note = catalog.note;
                    link.available = true;

                    const double point = (*outcome)["point"].AsDouble(std::numeric_limits<double>::quiet_NaN());
                    link.line = ReadString(*outcome, "name");
                    if (!std::isnan(point))
                    {
                        link.line += " ";
                        link.line += preferred_key == "spreads" ? FormatLineValue(point) : FormatPointValue(point);
                    }

                    const double model_probability = ClampDouble(static_cast<double>(prediction.confidence_value) / 100.0, 0.01, 0.99);
                    const std::optional<double> book_probability = AmericanToProbability(link.price);
                    link.fair_odds = ProbabilityToAmerican(model_probability);
                    if (book_probability.has_value())
                    {
                        link.book_probability = FormatPointValue(*book_probability * 100.0) + "%";
                        link.model_edge = FormatSignedPercent((model_probability - *book_probability) * 100.0);
                    }
                    else
                    {
                        link.book_probability = "--";
                        link.model_edge = prediction.edge;
                    }
                    return link;
                }
            }

            return std::nullopt;
        }

        BetLink KalshiSearchLink(const Game& game)
        {
            BetLink link;
            link.provider_key = "kalshi";
            link.title = "Kalshi";
            link.kind = "Prediction Exchange";
            link.url = "https://kalshi.com/markets?search=" + UrlEncode(game.away.name + " " + game.home.name);
            link.market = "Event contract";
            link.line = "Search contracts";
            link.price = "--";
            link.note = "Kalshi markets are event contracts, not sportsbook bets.";
            link.available = false;
            link.source = "Public market search";
            return link;
        }

        MarketAccessSummary EnrichMarketAccess(std::vector<Game>& games, std::vector<Prediction>& predictions, const std::string& configured_odds_key)
        {
            MarketAccessSummary summary;
            const std::vector<Bookmaker> catalog = BookmakerCatalog();
            summary.bookmakers = static_cast<int>(std::count_if(catalog.begin(), catalog.end(), [](const Bookmaker& book) {
                return book.kind == "Sportsbook";
            }));

            const std::string api_key = ConfiguredOddsApiKey(configured_odds_key);
            summary.odds_configured = !api_key.empty();
            if (summary.odds_configured)
            {
                summary.odds_status = "Checking";
                summary.odds_detail = "Key is configured; fetching direct sportsbook odds.";
            }
            std::map<std::string, std::vector<JsonValue>> odds_cache;
            std::map<std::string, Prediction*> predictions_by_game;
            const std::map<std::string, LineSnapshot> previous_snapshots = LoadMarketSnapshots();
            for (Prediction& prediction : predictions)
                predictions_by_game[prediction.game_id] = &prediction;

            for (Game& game : games)
            {
                Prediction scratch = BuildPredictionForGame(game, 0, SportsBucket(60));
                Prediction* prediction = predictions_by_game.contains(game.id) ? predictions_by_game[game.id] : &scratch;
                std::vector<BetLink> links;

                if (summary.odds_configured)
                {
                    const std::string sport_key = OddsSportKey(game);
                    if (!sport_key.empty())
                    {
                        if (!odds_cache.contains(sport_key))
                            odds_cache[sport_key] = FetchOddsEvents(sport_key, api_key, summary);
                        const std::optional<JsonValue> event = FindMatchingOddsEvent(game, odds_cache[sport_key]);
                        if (event.has_value())
                        {
                            ++summary.matched_events;
                            const JsonValue& bookmakers = (*event)["bookmakers"];
                            if (bookmakers.IsArray())
                            {
                                for (const JsonValue& bookmaker : bookmakers.array_value)
                                {
                                    const std::string odds_key = ReadString(bookmaker, "key");
                                    auto catalog_it = std::find_if(catalog.begin(), catalog.end(), [&odds_key](const Bookmaker& book) {
                                        return book.odds_key == odds_key;
                                    });
                                    if (catalog_it == catalog.end())
                                        continue;
                                    std::optional<BetLink> link = ExtractBookMarket(bookmaker, *catalog_it, game, *prediction);
                                    if (link.has_value())
                                    {
                                        const std::string key = SnapshotKey(game, *link);
                                        const auto previous = previous_snapshots.find(key);
                                        link->movement = MovementFromSnapshot(previous == previous_snapshots.end() ? nullptr : &previous->second, *link);
                                        links.push_back(*link);
                                        ++summary.available_lines;
                                    }
                                }
                            }
                        }
                    }
                }

                if (links.empty())
                {
                    for (const Bookmaker& book : catalog)
                    {
                        if (book.kind != "Sportsbook")
                            continue;
                        BetLink link;
                        link.provider_key = book.provider_key;
                        link.title = book.title;
                        link.kind = book.kind;
                        link.url = book.url;
                        link.market = prediction->market;
                        link.line = prediction->pick;
                        link.price = "--";
                        link.note = summary.odds_configured ? "No matched live line returned for this event. Open the book and verify manually." : "Add an Odds API key in Settings for direct sportsbook line matching.";
                        link.available = false;
                        link.source = summary.odds_configured ? "No match" : "Needs odds key";
                        link.movement = summary.odds_configured ? summary.odds_status : "Not tracking";
                        links.push_back(link);
                        if (links.size() >= 3)
                            break;
                    }
                }

                links.push_back(KalshiSearchLink(game));
                game.bet_links = links;

                auto best = std::find_if(links.begin(), links.end(), [](const BetLink& link) {
                    return link.available && link.kind == "Sportsbook" && link.price != "--";
                });
                if (best != links.end())
                {
                    prediction->best_book = best->title;
                    prediction->book_line = best->line;
                    prediction->odds = best->price;
                    prediction->edge = best->model_edge.empty() ? prediction->edge : best->model_edge;
                    prediction->input_count += 1;
                }
                prediction->market_links = links;
                prediction->comparison = BuildTeamComparison(game, *prediction);
            }

            AppendMarketSnapshots(games);
            if (summary.odds_configured && summary.odds_calls == 0)
            {
                summary.odds_status = "Configured";
                summary.odds_detail = "Odds key is saved, but this refresh had no supported sportsbook league calls.";
            }
            if (summary.odds_configured && summary.odds_calls > 0 && !summary.odds_reachable && summary.odds_errors > 0)
                summary.odds_status = "Key/error";
            return summary;
        }

        int CountGames(const std::vector<Game>& games, const std::string& status)
        {
            return static_cast<int>(std::count_if(games.begin(), games.end(), [&status](const Game& game) {
                return Lower(game.status_key) == status;
            }));
        }

        int CountMarketGames(const std::vector<Game>& games)
        {
            return static_cast<int>(std::count_if(games.begin(), games.end(), [](const Game& game) {
                return game.spread_favorite != "--" || game.total_over != "--";
            }));
        }

        std::vector<InfoItem> BuildCoverage(const std::vector<Game>& games, const std::vector<ProviderLeague>& active_leagues)
        {
            struct Counts
            {
                int configured = 0;
                int active = 0;
                int games = 0;
                int live = 0;
                int scheduled = 0;
                int final = 0;
            };
            std::map<std::string, Counts> groups;
            for (const ProviderLeague& league : ProviderLeagues())
                groups[league.group].configured++;
            for (const ProviderLeague& league : active_leagues)
                groups[league.group].active++;
            for (const Game& game : games)
            {
                Counts& counts = groups[game.sport_group.empty() ? "Sports" : game.sport_group];
                counts.games++;
                if (game.status_key == "live")
                    counts.live++;
                else if (game.status_key == "scheduled")
                    counts.scheduled++;
                else if (game.status_key == "final")
                    counts.final++;
            }

            std::vector<std::pair<std::string, Counts>> rows(groups.begin(), groups.end());
            std::sort(rows.begin(), rows.end(), [](const auto& left, const auto& right) {
                if (left.second.games != right.second.games)
                    return left.second.games > right.second.games;
                return left.first < right.first;
            });

            std::vector<InfoItem> out;
            for (const auto& [group, counts] : rows)
            {
                if (counts.games == 0 && counts.active == 0)
                    continue;
                InfoItem item;
                item.name = group;
                item.value = std::to_string(counts.games) + " games";
                item.detail = std::to_string(counts.live) + " live / " + std::to_string(counts.scheduled) + " upcoming";
                item.tag = std::to_string(counts.active) + " feeds scanned";
                out.push_back(item);
                if (out.size() >= 8)
                    break;
            }
            return out;
        }

        std::vector<InfoItem> BuildBookGrid(const std::vector<Game>& games)
        {
            std::vector<InfoItem> rows;
            for (const Game& game : games)
            {
                InfoItem item;
                const auto best = std::find_if(game.bet_links.begin(), game.bet_links.end(), [](const BetLink& link) {
                    return link.available && link.kind == "Sportsbook";
                });
                if (best != game.bet_links.end())
                {
                    item.book = best->title;
                    item.line = best->market + " " + best->line;
                    item.odds = best->price;
                    item.latency = best->source;
                }
                else
                {
                    const std::string market = game.spread_favorite != "--" ? game.spread_favorite : (game.total_over != "--" ? game.total_over : "Status only");
                    item.book = game.league + " feed";
                    item.line = market;
                    item.odds = game.status_label;
                    item.latency = game.feed_age_label;
                }
                rows.push_back(item);
                if (rows.size() >= 5)
                    break;
            }
            return rows;
        }

        std::vector<InfoItem> BuildOpportunityScanner(const std::vector<Game>& games, const std::vector<Prediction>& predictions, const MarketAccessSummary& market_access)
        {
            std::vector<InfoItem> rows;
            for (const Game& game : games)
            {
                for (const BetLink& link : game.bet_links)
                {
                    if (!link.available || link.kind != "Sportsbook")
                        continue;
                    InfoItem item;
                    item.name = link.movement.find("moved") != std::string::npos ? "Line movement" : "Value watch";
                    item.value = game.matchup;
                    item.weight = link.model_edge.empty() ? link.price : link.model_edge;
                    item.detail = link.title + " " + link.market + " " + link.line + " at " + link.price;
                    item.tag = link.movement;
                    item.status = game.status_label;
                    rows.push_back(item);
                    if (rows.size() >= 6)
                        return rows;
                }
            }

            for (const Prediction& prediction : predictions)
            {
                if (prediction.status_key == "final")
                    continue;
                InfoItem item;
                item.name = prediction.market + " watch";
                item.value = prediction.matchup;
                item.weight = prediction.confidence;
                item.detail = prediction.reason;
                item.tag = prediction.status_label;
                rows.push_back(item);
                if (rows.size() >= 5)
                    break;
            }

            if (!market_access.odds_configured)
            {
                rows.push_back({"Odds setup", "", "Ready", "", "Save an Odds API key in Settings to turn sportsbook links into matched line scans.", "Setup"});
            }
            return rows;
        }

        std::vector<InfoItem> BuildAlerts(const std::vector<Game>& games)
        {
            std::vector<InfoItem> alerts;
            for (const Game& game : games)
            {
                const auto moved = std::find_if(game.bet_links.begin(), game.bet_links.end(), [](const BetLink& link) {
                    return link.available && link.movement.find("moved") != std::string::npos;
                });
                if (moved != game.bet_links.end())
                {
                    InfoItem movement;
                    movement.name = "Line movement";
                    movement.detail = game.matchup + " " + moved->market + " moved at " + moved->title + ".";
                    movement.time = game.feed_age_label.empty() ? "Now" : game.feed_age_label;
                    movement.tag = moved->movement;
                    alerts.push_back(movement);
                    if (alerts.size() >= 4)
                        break;
                }

                InfoItem item;
                item.name = game.status_label.empty() ? "Board update" : game.status_label;
                item.detail = game.matchup + " is currently " + Lower(game.detail.empty() ? game.clock : game.detail) + ".";
                item.time = game.feed_age_label.empty() ? "Now" : game.feed_age_label;
                alerts.push_back(item);
                if (alerts.size() >= 4)
                    break;
            }
            return alerts;
        }

        std::vector<InfoItem> BuildTape(const std::vector<Game>& games)
        {
            std::vector<InfoItem> tape;
            for (const Game& game : games)
            {
                InfoItem item;
                item.name = game.league;
                item.value = game.matchup;
                item.state = game.status_label;
                tape.push_back(item);
                if (tape.size() >= 5)
                    break;
            }
            return tape;
        }
    }

    SportsState MakeDemoSportsState()
    {
        SportsState state;
        state.loaded_from_api = false;
        state.tier = "pro";
        state.source_badge = "Demo feed";
        state.source_label = "Demo board mirrors the native layout until an account session starts direct provider refresh.";

        state.games = {
            {"demo-nba-lal-gsw", "NBA", "nba", "Basketball", "LAL @ GSW", "live", "Live", "live", "Q3 06:12", "Lakers are controlling pace while Warriors are struggling in the paint.", "Now", "Chase Center",
                {"Lakers", "LAL", "LAL", "48-34", 78, false}, {"Warriors", "GSW", "GSW", "44-38", 72, false}, "-4.5", "+4.5", "O 224.5", "U 224.5",
                { 64, 68, 66, 75, 69, 73, 82, 78 },
                { {"draftkings", "DraftKings", "Sportsbook", "https://sportsbook.draftkings.com", "Spread", "Lakers -4.5", "-110", "Verify eligibility and final price before wagering.", true},
                  {"fanduel", "FanDuel", "Sportsbook", "https://sportsbook.fanduel.com", "Total", "Over 224.5", "-105", "Verify the final number before action.", true} }},
            {"demo-nfl-kc-buf", "NFL", "nfl", "Football", "KC @ BUF", "live", "Live", "live", "Q2 03:45", "Chiefs pressure rate is forcing shorter Bills drives.", "Now", "Highmark Stadium",
                {"Chiefs", "KC", "KC", "12-5", 14, false}, {"Bills", "BUF", "BUF", "11-6", 10, false}, "-3.5", "+3.5", "O 48.5", "U 48.5",
                { 54, 58, 63, 61, 67, 69, 71, 69 },
                { {"betmgm", "BetMGM", "Sportsbook", "https://sports.betmgm.com", "Spread", "Chiefs -3.5", "-115", "Confirm current odds before wagering.", true} }},
            {"demo-mlb-nyy-bos", "MLB", "mlb", "Baseball", "NYY @ BOS", "live", "Live", "live", "Top 6th", "Yankees bullpen edge is widening late-game projection.", "2m ago", "Fenway Park",
                {"Yankees", "NYY", "NYY", "21-11", 3, false}, {"Red Sox", "BOS", "BOS", "17-16", 1, false}, "-1.5", "+1.5", "O 8.5", "U 8.5",
                { 42, 47, 51, 55, 61, 68, 70, 74 },
                { {"caesars", "Caesars", "Sportsbook", "https://www.caesars.com/sportsbook-and-casino", "Run Line", "Yankees -1.5", "+125", "Open provider and verify location.", true} }},
            {"demo-ufc-main", "UFC", "ufc", "Combat", "Pereira vs Prochazka", "live", "Live", "live", "Round 2", "Power-shot volume is trending toward Pereira.", "Now", "T-Mobile Arena",
                {"D. Pereira", "DP", "DP", "", 0, false}, {"J. Prochazka", "JP", "JP", "", 0, false}, "--", "--", "--", "--",
                { 58, 60, 62, 64, 66, 65, 68, 65 },
                { {"espnbet", "ESPN BET", "Sportsbook", "https://espnbet.com", "Method of Victory", "Pereira KO/TKO", "+220", "Combat markets move quickly; verify before action.", true} }},
            {"demo-soc-mci-ars", "Soccer", "eng.1", "Soccer", "Man City vs Arsenal", "final", "Final", "final", "FT", "Final retained for audit and model grading.", "8m ago", "Etihad Stadium",
                {"Man City", "MCI", "MCI", "", 2, true}, {"Arsenal", "ARS", "ARS", "", 1, false}, "-0.5", "+0.5", "O 2.5", "U 2.5",
                { 50, 51, 58, 57, 64, 69, 73, 76 },
                { {"kalshi", "Kalshi", "Prediction Exchange", "https://kalshi.com/markets?search=Man%20City%20Arsenal", "Event contract", "Search contracts", "--", "Event-contract rules differ from sportsbook odds.", false} }}
        };

        state.predictions = {
            {"demo-nba-lal-gsw", "Lakers -4.5", "Lakers", "LAL @ GSW", "Spread", "NBA", "Basketball", "live", "Live", "78%", 78, "-110", "-355", "78.0%", "+12.4%", "$24.80", "Live volatility", "Lakers paint pressure and defensive rebounding are creating a model edge while the market is still near the opener.", "DraftKings", "Lakers -4.5", true},
            {"demo-nba-lal-gsw", "Over 224.5", "Over", "LAL @ GSW", "Total", "NBA", "Basketball", "live", "Live", "72%", 72, "-105", "-257", "72.0%", "+9.7%", "$19.40", "Pace risk", "Shot volume and transition possessions are running above the pregame total profile.", "FanDuel", "Over 224.5", true},
            {"demo-nfl-kc-buf", "Chiefs -3.5", "Chiefs", "KC @ BUF", "Spread", "NFL", "Football", "live", "Live", "69%", 69, "-115", "-223", "69.0%", "+8.6%", "$17.20", "Weather and live variance", "Chiefs defensive pressure and field position are creating a controlled spread profile.", "BetMGM", "Chiefs -3.5", true},
            {"demo-ufc-main", "Pereira by KO", "Pereira", "Pereira vs Prochazka", "Method of Victory", "UFC", "Combat", "live", "Live", "65%", 65, "+220", "-186", "65.0%", "+15.3%", "$33.60", "High volatility", "Power-shot differential gives the method market a positive watch rating.", "ESPN BET", "Pereira KO/TKO", true},
            {"demo-soc-mci-ars", "Postgame review: Man City", "Man City", "Man City vs Arsenal", "Audit", "Soccer", "Soccer", "final", "Final", "61%", 61, "--", "-156", "61.0%", "+0.0%", "$0.00", "Closed market", "Finals remain visible for model grading and interface accuracy.", "Provider links", "Closed", false}
        };

        for (Prediction& pick : state.predictions)
        {
            pick.steps = {
                {"1. Start neutral", "", "50%", "", "Every pick starts at 50% before evidence is added."},
                {"2. Add game context", "", pick.status_label, "", "Live, scheduled, and final games receive different context weights."},
                {"3. Add market evidence", "", pick.market, "", "Spread, total, book depth, and line movement are compared against model probability."},
                {"4. Subtract uncertainty", "", "Applied", "", "Missing injuries, lineups, and advanced tracking keep confidence conservative."},
                {"5. Final confidence", "", pick.confidence, "", "This is an informational estimate, not a promise that the pick wins."}
            };
            pick.factors = {
                {"Core team strength", "", "+6 pts", "", "Records, score margin, league context, and line snapshots are blended.", "", "Active public feed"},
                {"Recent form", "", "Proxy", "", "Confidence history is used when team logs are incomplete.", "", "Partial"},
                {"Betting market data", "", pick.edge, "", "Provider prices improve edge calculations when available.", "", "Active"},
                {"Injuries and lineups", "", "Manual check", "", "Official reports and late scratches must still be verified.", "", "Needs setup"}
            };
            pick.missing_inputs = {
                {"Injury feed", "", "Manual check", "", "Connect a verified injury or lineup source before treating availability as confirmed."},
                {"Execution mode", "", "Informational", "", "Aegis opens market links for review; it does not place real bets."}
            };
        }

        state.factors = {
            {"Live scoreboard", "", "", "4 live", "Current status, clock, scores, and market snapshots are treated as the highest-value live inputs."},
            {"Market coverage", "", "", "5 with lines", "Spread and total displays only appear when provider snapshots exist."},
            {"Book access", "", "", "Links ready", "Provider links are outbound access, not automated betting."},
            {"Refresh health", "", "", "Healthy", "Local UI refreshes independently from provider cache cadence."}
        };
        state.coverage = {
            {"Basketball", "", "2 games", "", "1 live / 1 upcoming"},
            {"Football", "", "1 game", "", "1 live / 0 upcoming"},
            {"Baseball", "", "1 game", "", "1 live / 0 upcoming"},
            {"Soccer", "", "1 game", "", "0 live / 0 upcoming"},
            {"Combat", "", "1 game", "", "1 live / 0 upcoming"}
        };
        state.providers = {
            {"Odds feed", "", "Demo", "", "The Odds API / provider links"},
            {"Bookmakers", "", "6", "", "Outbound app links"},
            {"Matched lines", "", "4", "", "Demo matched events"},
            {"Exchange scan", "", "Kalshi", "", "Public search links"}
        };
        state.books = {
            {"", "", "", "", "", "", "", "DraftKings", "Lakers -4.5", "-110", "Live"},
            {"", "", "", "", "", "", "", "FanDuel", "Over 224.5", "-105", "Live"},
            {"", "", "", "", "", "", "", "BetMGM", "Chiefs -3.5", "-115", "Live"}
        };
        state.opportunities = {
            {"Line movement", "", "LAL @ GSW", "", "Spread moved from -3.5 to -4.5.", "Active"},
            {"Value bet", "", "Chiefs -3.5", "", "Current line shows 8.6% model edge.", "Ready"},
            {"Model health", "", "Active", "", "Direct-source model checks are active.", "Useful"}
        };
        state.edge_stack = {
            {"Live board", "", "4", "", "Current live matchups on board."},
            {"Covered markets", "", "5", "", "Games with spread or total snapshots."},
            {"Freshness", "", "Healthy", "", "Current UI data freshness posture."}
        };
        state.rules = {
            {"Status honesty", "", "", "", "Final games stay marked final and are not presented as new live opportunities.", "", "Active"},
            {"Execution mode", "", "", "", "Informational only. No automatic sportsbook execution.", "", "Informational"},
            {"Confidence cap", "", "", "", "Confidence remains clipped while injury and lineup feeds are incomplete.", "", "Conservative"}
        };
        state.alerts = {
            {"Line Movement", "", "", "", "LAL @ GSW spread moved from -3.5 to -4.5.", "", "", "", "", "", "", "2m ago"},
            {"Value Bet", "", "", "", "Chiefs -3.5 now has 8.6% more model value.", "", "", "", "", "", "", "5m ago"},
            {"Model Health", "", "", "", "Direct-source model checks are active.", "", "", "", "", "", "", "1h ago"}
        };
        state.performance = {
            {"Live status accuracy", "", "Demo board", "", "Use account login to start direct provider refresh."},
            {"Market depth", "", "5", "", "Demo markets available for UI testing."},
            {"Refresh health", "", "Local", "", "Desktop refresh timer is ready."}
        };
        state.tape = {
            {"NBA", "", "LAL @ GSW", "", "", "Live"},
            {"NFL", "", "KC @ BUF", "", "", "Live"},
            {"MLB", "", "NYY @ BOS", "", "", "Live"},
            {"UFC", "", "Pereira vs Prochazka", "", "", "Live"}
        };
        state.model_sources = {
            {"Scoreboard and live state", "", "", "", "Game status, clock, score, league, and freshness refresh through public scoreboard feeds.", "", "", "", "", "", "", "Built-in public scoreboard feed", "Connected"},
            {"Sportsbook odds and line movement", "", "", "", "Add an Odds API key in Settings for live bookmaker line snapshots.", "", "", "", "", "", "", "The Odds API", "Needs API key"},
            {"Simulation and ML engine", "", "", "", "Feature schema is ready for local simulation and trained model inputs.", "", "", "", "", "", "", "Local model pipeline", "Designed"}
        };
        state.insight_copy = "Our AI model predicts value on Lakers -4.5 due to 62% win-rate factors and Warriors' poor interior defense.";
        state.primary_market = "LAL @ GSW - Spread";
        state.selected_market = "Lakers -4.5";
        state.book_opened = "-3.5";
        state.book_current = "-4.5";
        return state;
    }

    SportsState BuildNativeSportsState(int tracked_games, int model_count, int refresh_seconds, const std::string& odds_api_key)
    {
        tracked_games = ClampInt(tracked_games <= 0 ? 100 : tracked_games, 12, 160);
        model_count = ClampInt(model_count <= 0 ? 10 : model_count, 2, 32);
        refresh_seconds = ClampInt(refresh_seconds <= 0 ? 60 : refresh_seconds, 5, 3600);

        const int bucket = SportsBucket(refresh_seconds);
        const std::vector<ProviderLeague> active_leagues = ActiveProviderLeagues(tracked_games, refresh_seconds);
        std::vector<Game> games = FetchNativeGames(tracked_games, refresh_seconds);
        std::vector<Prediction> predictions = BuildNativePredictions(games, model_count, bucket);
        const MarketAccessSummary market_access = EnrichMarketAccess(games, predictions, odds_api_key);
        AppendPredictionAudit(games, predictions);
        const AuditSummary audit = ReadAuditSummary();

        const int live_games = CountGames(games, "live");
        const int final_games = CountGames(games, "final");
        const int scheduled_games = CountGames(games, "scheduled");
        const int market_games = CountMarketGames(games);
        const bool fallback_board = !games.empty() && games.front().feed_age_label == "Fallback";

        SportsState state;
        state.loaded_from_api = true;
        state.tier = "pro";
        state.source_badge = fallback_board ? "Fallback board" : "Native live feed";
        state.source_label = fallback_board
            ? "Direct provider fetch did not return a usable slate, so Aegis is showing a clearly marked fallback board."
            : "Direct ESPN source feed. Odds API optional.";
        state.games = std::move(games);
        state.predictions = std::move(predictions);

        state.factors = {
            {"Live scoreboard", "", "", std::to_string(live_games) + " live", fallback_board ? "Provider scoreboard is unavailable, so this board is marked fallback." : "Current event status comes from direct multi-league provider scoreboards."},
            {"Pregame board", "", "", std::to_string(scheduled_games) + " upcoming", "Scheduled matchups stay on the board so users can monitor the next betting windows."},
            {"Final audit set", "", "", std::to_string(final_games) + " final", "Completed games are kept for review and grading instead of still being labeled live."},
            {"Sports universe", "", "", std::to_string(static_cast<int>(ProviderLeagues().size())) + " leagues", "The desktop app rotates lower-volume sports through the same scan queue style as the website."},
            {"Market coverage", "", "", std::to_string(market_games) + " with lines", "Spread and total displays only show provider snapshots when a source supplies them."},
            {"Book access", "", "", market_access.available_lines > 0 ? std::to_string(market_access.available_lines) + " live lines" : market_access.odds_status, market_access.odds_configured ? market_access.odds_detail : "Add an Odds API key in Settings to enable direct sportsbook line matching."},
            {"Refresh health", "", "", "Now", "Refresh runs inside the desktop app and fetches from provider hosts directly."}
        };

        state.coverage = BuildCoverage(state.games, active_leagues);
        state.providers = {
            {"Odds feed", "", market_access.odds_status, "", market_access.odds_detail},
            {"Bookmakers", "", std::to_string(market_access.bookmakers), "", "Outbound app links and direct odds matching"},
            {"Matched lines", "", std::to_string(market_access.available_lines), "", std::to_string(market_access.matched_events) + " matched events"},
            {"Exchange scan", "", "Kalshi", "", "Public market search links"}
        };
        state.books = BuildBookGrid(state.games);
        state.opportunities = BuildOpportunityScanner(state.games, state.predictions, market_access);
        state.edge_stack = {
            {"Live board", "", std::to_string(live_games), "", "Number of matchups currently flagged live by the provider feed."},
            {"Covered markets", "", std::to_string(market_games), "", "Games that currently include a spread or total snapshot."},
            {"Freshness", "", "Now", "", "The refresh thread just rebuilt the board from direct sources."},
            {"Line movement", "", std::to_string(market_access.available_lines), "", "Matched sportsbook prices are cached locally so movement labels can compare refreshes."},
            {"Audit set", "", std::to_string(final_games), "", "Final games retained for postgame analysis and model grading."}
        };
        state.rules = {
            {"Status honesty", "", "", "", "Final games stay marked final and are not presented as new live opportunities.", "", "Active"},
            {"Execution mode", "", "", "", "Informational only. No automatic sportsbook execution.", "", "Informational"},
            {"Direct-source rule", "", "", "", "Sports data is fetched from provider hosts in the desktop app, not from the website sports endpoint.", "", "Active"},
            {"Stale-data guard", "", "", "", "Rows without direct sportsbook prices or fresh scoreboards remain monitor/manual-verify signals.", "", "Active"},
            {"Confidence cap", "", "", "", "Confidence remains clipped while injury and lineup feeds are incomplete.", "", "Conservative"}
        };
        state.alerts = BuildAlerts(state.games);
        state.performance = {
            {"Source mode", "", "Native", "", "Desktop app calls provider hosts directly for the sports board."},
            {"Events loaded", "", std::to_string(static_cast<int>(state.games.size())), "", "Live, upcoming, and final events are available for filtering."},
            {"Market depth", "", std::to_string(market_access.available_lines), "", market_access.odds_configured ? market_access.odds_detail : "No odds key configured; using scoreboard market snapshots and provider links."},
            {"Refresh cadence", "", std::to_string(refresh_seconds) + "s", "", "Background refresh uses the desktop timer."},
            {"Local audit", "", std::to_string(audit.graded) + "/" + std::to_string(audit.samples), "", audit.samples > 0 ? "Predictions are persisted locally for grading and calibration." : "Audit trail starts after the first native refresh."},
            {"Audit win rate", "", audit.graded > 0 ? FormatPointValue(static_cast<double>(audit.wins) * 100.0 / static_cast<double>(audit.graded)) + "%" : "--", "", "Only final games with a resolved predicted side are counted."}
        };
        state.tape = BuildTape(state.games);
        state.model_sources = {
            {"Scoreboard and live state", "", "", "", "Game status, clock, score, league, and freshness refresh from direct ESPN public scoreboards.", "", "", "", "", "", "", "Native provider fetch", "Connected"},
            {"Sportsbook odds and line movement", "", "", "", market_access.odds_detail, "", "", "", "", "", "", "The Odds API", market_access.odds_status},
            {"Exchange access", "", "", "", "Kalshi is linked as public event-contract search. Users must verify product rules manually.", "", "", "", "", "", "", "Kalshi public markets", "Linked"},
            {"Simulation and ML engine", "", "", "", "Feature schema is ready for local simulation and trained model inputs.", "", "", "", "", "", "", "Local model pipeline", "Designed"}
        };
        state.metrics = {
            {"Events", "", std::to_string(static_cast<int>(state.games.size())), "", "Total events on the direct-source board."},
            {"Live", "", std::to_string(live_games), "", "Events currently in progress."},
            {"Upcoming", "", std::to_string(scheduled_games), "", "Scheduled events in the scan window."},
            {"Final", "", std::to_string(final_games), "", "Completed events retained for audit."},
            {"Markets", "", std::to_string(market_games), "", "Events with spread or total snapshots."},
            {"Book lines", "", std::to_string(market_access.available_lines), "", "Matched direct sportsbook prices this refresh."}
        };

        if (!state.predictions.empty())
        {
            const Prediction& top = state.predictions.front();
            state.insight_title = "Why this market matters now";
            state.insight_copy = top.reason;
            state.primary_market = top.matchup + " - " + top.market;
            state.selected_market = top.book_line.empty() ? top.pick : top.book_line;
            state.book_current = top.odds.empty() ? "--" : top.odds;
            state.book_opened = "Source";
        }
        if (!state.games.empty())
            state.market_history = state.games.front().history;

        return state;
    }

    OddsValidationResult ValidateOddsApiKey(const std::string& odds_api_key)
    {
        OddsValidationResult result;
        const std::string key = ConfiguredOddsApiKey(odds_api_key);
        result.configured = !key.empty();
        if (key.empty())
        {
            result.status = "No key saved";
            result.detail = "Create a key at The Odds API, paste it here, then press Validate Key.";
            return result;
        }

        const std::string url = "https://api.the-odds-api.com/v4/sports/?apiKey=" + UrlEncode(key);
        const HttpResponse response = HttpGet(url);
        result.status_code = response.status_code;
        result.requests_remaining = HeaderValue(response.raw_headers, "x-requests-remaining");
        result.requests_used = HeaderValue(response.raw_headers, "x-requests-used");
        result.requests_last = HeaderValue(response.raw_headers, "x-requests-last");

        if (!response.error.empty())
        {
            result.status = "Network error";
            result.detail = response.error;
            return result;
        }
        if (response.status_code == 401 || response.status_code == 403)
        {
            result.status = "Rejected";
            result.detail = "The Odds API rejected this key. Check that it was copied correctly.";
            return result;
        }
        if (response.status_code == 429)
        {
            result.status = "Rate limited";
            result.detail = "The key is valid enough to reach the API, but the quota is exhausted right now.";
            return result;
        }
        if (response.status_code < 200 || response.status_code >= 300)
        {
            result.status = "HTTP " + std::to_string(response.status_code);
            result.detail = "The Odds API returned a non-success response.";
            return result;
        }

        const JsonParseResult parsed = ParseJson(response.body);
        if (!parsed.ok || !parsed.value.IsArray())
        {
            result.status = "Parse error";
            result.detail = "The key reached The Odds API, but the sports list response was not readable.";
            return result;
        }

        result.ok = true;
        result.sports = static_cast<int>(parsed.value.array_value.size());
        result.status = "Connected";
        result.detail = "Key validated. Direct sportsbook odds can now be matched during refresh.";
        return result;
    }

    ParseSportsResult ParseSportsApiResponse(const std::string& body)
    {
        ParseSportsResult result;
        result.state = MakeDemoSportsState();
        const JsonParseResult parsed = ParseJson(body);
        if (!parsed.ok)
        {
            result.message = parsed.error;
            return result;
        }

        if (!parsed.value["ok"].AsBool(false))
        {
            result.message = parsed.value["message"].AsString("Sports API returned an error.");
            return result;
        }

        const JsonValue& root_state = parsed.value["state"];
        if (!root_state.IsObject())
        {
            result.message = "Sports API did not return a state object.";
            return result;
        }

        SportsState state;
        state.loaded_from_api = true;
        state.tier = parsed.value["tier"].AsString(root_state["tier"].AsString("free"));
        state.source_badge = ReadString(root_state, "sourceBadge", "Live feed");
        state.source_label = ReadString(root_state, "sourceLabel", "Live public scoreboard + Aegis modeling");
        state.insight_title = ReadString(root_state["insight"], "title", state.insight_title);
        state.insight_copy = ReadString(root_state["insight"], "copy", state.insight_copy);
        state.primary_market = ReadString(root_state, "primaryMarket", state.primary_market);
        state.selected_market = ReadString(root_state, "selectedMarket", state.selected_market);
        state.market_history = ParseFloatArray(root_state["marketHistory"], state.market_history);
        state.book_opened = ReadString(root_state["bookSummary"], "opened", "--");
        state.book_current = ReadString(root_state["bookSummary"], "current", "--");

        if (root_state["games"].IsArray())
        {
            for (const JsonValue& game : root_state["games"].array_value)
                state.games.push_back(ParseGame(game));
        }
        if (root_state["predictions"].IsArray())
        {
            for (const JsonValue& prediction : root_state["predictions"].array_value)
                state.predictions.push_back(ParsePrediction(prediction));
        }

        state.factors = ParseInfoArray(root_state["factors"]);
        state.books = ParseInfoArray(root_state["books"]);
        state.opportunities = ParseInfoArray(root_state["opportunities"]);
        state.edge_stack = ParseInfoArray(root_state["edgeStack"]);
        state.rules = ParseInfoArray(root_state["rules"]);
        state.alerts = ParseInfoArray(root_state["alerts"]);
        state.performance = ParseInfoArray(root_state["performance"]);
        state.tape = ParseInfoArray(root_state["tape"]);
        state.model_sources = ParseInfoArray(root_state["modelSources"]);
        state.metrics = ParseInfoArray(root_state["metrics"]);

        if (root_state["coverage"]["groups"].IsArray())
        {
            for (const JsonValue& group : root_state["coverage"]["groups"].array_value)
            {
                InfoItem item;
                item.name = ReadString(group, "label", "Sports");
                item.value = std::to_string(ReadInt(group, "games", 0)) + " games";
                item.detail = std::to_string(ReadInt(group, "live", 0)) + " live / " + std::to_string(ReadInt(group, "scheduled", 0)) + " upcoming";
                state.coverage.push_back(item);
            }
        }

        const JsonValue& access = root_state["marketAccess"];
        state.providers = {
            {"Odds feed", "", access["oddsProviderConfigured"].AsBool(false) ? "Connected" : "Needs API key", "", ReadString(access, "oddsProvider", "The Odds API")},
            {"Bookmakers", "", std::to_string(ReadInt(access, "bookmakers", 0)), "", "Outbound app links"},
            {"Matched lines", "", std::to_string(ReadInt(access, "availableLines", 0)), "", std::to_string(ReadInt(access, "matchedEvents", 0)) + " matched events"},
            {"Exchange scan", "", ReadString(access, "exchangeProvider", "Kalshi"), "", std::to_string(ReadInt(access, "kalshiMarketsCached", 0)) + " cached markets"}
        };

        if (state.games.empty() || state.predictions.empty())
        {
            SportsState demo = MakeDemoSportsState();
            if (state.games.empty())
                state.games = demo.games;
            if (state.predictions.empty())
                state.predictions = demo.predictions;
        }

        result.ok = true;
        result.message = "Sports state synchronized.";
        result.state = state;
        return result;
    }

    std::string TeamMark(const Team& team)
    {
        std::string source = !team.abbr.empty() ? team.abbr : team.name;
        std::string out;
        for (const char c : source)
        {
            if (std::isalnum(static_cast<unsigned char>(c)) != 0)
                out.push_back(static_cast<char>(std::toupper(static_cast<unsigned char>(c))));
            if (out.size() == 2)
                break;
        }
        return out.empty() ? "TM" : out;
    }

    std::string PredictionWinner(const Prediction& prediction, const SportsState& state)
    {
        if (!prediction.predicted_winner.empty())
            return prediction.predicted_winner;
        for (const Game& game : state.games)
        {
            if (game.id != prediction.game_id)
                continue;
            const std::string pick = Lower(prediction.pick);
            if (!game.away.name.empty() && pick.find(Lower(game.away.name)) != std::string::npos)
                return game.away.name;
            if (!game.home.name.empty() && pick.find(Lower(game.home.name)) != std::string::npos)
                return game.home.name;
            if (!game.away.abbr.empty() && pick.find(Lower(game.away.abbr)) != std::string::npos)
                return game.away.name;
            if (!game.home.abbr.empty() && pick.find(Lower(game.home.abbr)) != std::string::npos)
                return game.home.name;
        }
        return prediction.pick.empty() ? "Predicted winner" : prediction.pick;
    }

    std::vector<const Game*> FilterGames(const SportsState& state, const std::string& filter, const std::string& search)
    {
        std::vector<const Game*> games;
        const std::string query = Lower(Trim(search));
        for (const Game& game : state.games)
        {
            if (!GameMatchesFilter(game, filter))
                continue;
            if (!query.empty())
            {
                const std::string haystack = SearchHaystack(game);
                std::stringstream stream(query);
                std::string term;
                bool all_terms = true;
                while (stream >> term)
                {
                    if (!ContainsToken(haystack, term))
                    {
                        all_terms = false;
                        break;
                    }
                }
                if (!all_terms)
                    continue;
            }
            games.push_back(&game);
        }
        return games;
    }

    std::vector<int> FilterPredictionIndexes(const SportsState& state, const std::string& filter, const std::string& search)
    {
        const std::vector<const Game*> filtered = FilterGames(state, filter, search);
        std::vector<std::string> game_ids;
        for (const Game* game : filtered)
            game_ids.push_back(game->id);
        std::vector<int> indexes;
        for (int i = 0; i < static_cast<int>(state.predictions.size()); ++i)
        {
            const Prediction& prediction = state.predictions[static_cast<size_t>(i)];
            if (filter == "all" && Trim(search).empty())
            {
                indexes.push_back(i);
                continue;
            }
            if (std::find(game_ids.begin(), game_ids.end(), prediction.game_id) != game_ids.end())
                indexes.push_back(i);
        }
        return indexes;
    }

    double DecimalFromAmerican(const std::string& odds_text)
    {
        int sign = 1;
        std::string digits;
        for (const char c : odds_text)
        {
            if ((c == '+' || c == '-') && digits.empty())
                sign = c == '-' ? -1 : 1;
            else if (std::isdigit(static_cast<unsigned char>(c)) != 0)
                digits.push_back(c);
        }
        int odds = digits.empty() ? -110 : std::atoi(digits.c_str()) * sign;
        if (odds == 0)
            odds = -110;
        return odds > 0 ? 1.0 + (static_cast<double>(odds) / 100.0) : 1.0 + (100.0 / std::abs(static_cast<double>(odds)));
    }

    double ToWin(const std::string& odds_text, double stake)
    {
        return std::max(0.0, stake) * (DecimalFromAmerican(odds_text) - 1.0);
    }

    std::string AmericanFromDecimal(double decimal)
    {
        if (decimal <= 1.0)
            return "--";
        const int odds = decimal >= 2.0
            ? static_cast<int>(std::round((decimal - 1.0) * 100.0))
            : static_cast<int>(std::round(-100.0 / (decimal - 1.0)));
        return odds > 0 ? "+" + std::to_string(odds) : std::to_string(odds);
    }
}
