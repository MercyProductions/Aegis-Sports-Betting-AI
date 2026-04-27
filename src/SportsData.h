#pragma once

#include "Json.h"

#include <string>
#include <vector>

namespace aegis
{
    struct Team
    {
        std::string name;
        std::string abbr;
        std::string short_name;
        std::string record;
        int score = 0;
        bool winner = false;
        std::string logo;
        std::string probability;
        int rating = 0;
    };

    struct BetLink
    {
        std::string provider_key;
        std::string title;
        std::string kind;
        std::string url;
        std::string market;
        std::string line;
        std::string price;
        std::string note;
        bool available = false;
        std::string fair_odds;
        std::string book_probability;
        std::string model_edge;
        std::string source;
        std::string last_update;
        std::string movement;
    };

    struct Game
    {
        std::string id;
        std::string league;
        std::string league_key;
        std::string sport_group;
        std::string matchup;
        std::string status_key;
        std::string status_label;
        std::string status_tone;
        std::string clock;
        std::string detail;
        std::string feed_age_label;
        std::string venue;
        Team away;
        Team home;
        std::string spread_favorite;
        std::string spread_other;
        std::string total_over;
        std::string total_under;
        std::vector<float> history;
        std::vector<BetLink> bet_links;
        std::string freshness_state;
        std::string source_note;
        std::string source_timestamp;
        std::string start_time;
        std::string odds_match_status;
        std::string odds_match_detail;
    };

    struct InfoItem
    {
        std::string name;
        std::string label;
        std::string value;
        std::string weight;
        std::string detail;
        std::string tag;
        std::string state;
        std::string book;
        std::string line;
        std::string odds;
        std::string latency;
        std::string env;
        std::string status;
        std::string away;
        std::string home;
        std::string edge;
        std::string time;
        std::string source;
    };

    struct TeamComparison
    {
        Team away;
        Team home;
        std::string pick_side;
        std::string summary;
        std::vector<InfoItem> rows;
    };

    struct Prediction
    {
        std::string game_id;
        std::string pick;
        std::string predicted_winner;
        std::string matchup;
        std::string market;
        std::string league;
        std::string sport_group;
        std::string status_key;
        std::string status_label;
        std::string confidence;
        int confidence_value = 50;
        std::string odds;
        std::string fair_odds;
        std::string fair_probability;
        std::string edge;
        std::string expected_value;
        std::string risk;
        std::string reason;
        std::string best_book;
        std::string book_line;
        bool can_bet = true;
        std::vector<BetLink> market_links;
        std::vector<InfoItem> steps;
        std::vector<InfoItem> factors;
        std::vector<InfoItem> missing_inputs;
        TeamComparison comparison;
        std::string model_version;
        std::string source_timestamp;
        std::string data_trust;
        std::string confidence_band;
        int input_count = 0;
        int missing_input_penalty = 0;
    };

    struct SportsState
    {
        bool loaded_from_api = false;
        std::string tier = "free";
        std::string source_badge = "Demo feed";
        std::string source_label = "Built-in demo slate. Sign in to build the native direct-source board.";
        std::vector<Game> games;
        std::vector<Prediction> predictions;
        std::vector<InfoItem> factors;
        std::vector<InfoItem> coverage;
        std::vector<InfoItem> providers;
        std::vector<InfoItem> books;
        std::vector<InfoItem> opportunities;
        std::vector<InfoItem> edge_stack;
        std::vector<InfoItem> rules;
        std::vector<InfoItem> alerts;
        std::vector<InfoItem> performance;
        std::vector<InfoItem> tape;
        std::vector<InfoItem> model_sources;
        std::vector<InfoItem> metrics;
        std::vector<InfoItem> diagnostics;
        std::vector<InfoItem> provider_sports;
        std::string insight_title = "Why this market matters now";
        std::string insight_copy = "Aegis is watching status, price movement, and model confidence so the board stays useful without pretending uncertainty is certainty.";
        std::string primary_market = "LAL @ GSW - Spread";
        std::string selected_market = "Lakers -4.5";
        std::vector<float> market_history = { 48, 52, 44, 56, 62, 58, 70, 66, 72 };
        std::string book_opened = "-3.5";
        std::string book_current = "-4.5";
    };

    struct ParseSportsResult
    {
        bool ok = false;
        std::string message;
        SportsState state;
    };

    struct OddsValidationResult
    {
        bool configured = false;
        bool ok = false;
        int status_code = 0;
        int sports = 0;
        std::string status;
        std::string detail;
        std::string requests_remaining;
        std::string requests_used;
        std::string requests_last;
    };

    struct OptionalFeedValidationResult
    {
        bool ok = false;
        bool parsed = false;
        bool reachable = false;
        int status_code = 0;
        int records = 0;
        int errors = 0;
        int warnings = 0;
        std::string feed_key;
        std::string title;
        std::string contract;
        std::string status;
        std::string detail;
        std::string source_timestamp;
        std::vector<InfoItem> issues;
    };

    SportsState MakeDemoSportsState();
    SportsState BuildNativeSportsState(int tracked_games, int model_count, int refresh_seconds, const std::string& odds_api_key = "");
    OddsValidationResult ValidateOddsApiKey(const std::string& odds_api_key);
    std::vector<InfoItem> OptionalFeedSchemaRows();
    std::string OptionalFeedContractLabel(const std::string& feed_key);
    OptionalFeedValidationResult ValidateOptionalFeedBody(const std::string& feed_key, const std::string& body);
    void ApplyOptionalFeedSignals(SportsState& state, const std::vector<OptionalFeedValidationResult>& feeds);
    ParseSportsResult ParseSportsApiResponse(const std::string& body);
    std::string TeamMark(const Team& team);
    std::string PredictionWinner(const Prediction& prediction, const SportsState& state);
    std::vector<const Game*> FilterGames(const SportsState& state, const std::string& filter, const std::string& search);
    std::vector<int> FilterPredictionIndexes(const SportsState& state, const std::string& filter, const std::string& search);
    double DecimalFromAmerican(const std::string& odds_text);
    double ToWin(const std::string& odds_text, double stake);
    std::string AmericanFromDecimal(double decimal);
}
