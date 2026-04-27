#pragma once

#include <map>
#include <string>
#include <vector>

namespace aegis
{
    class JsonValue
    {
    public:
        enum class Type
        {
            Null,
            Bool,
            Number,
            String,
            Array,
            Object
        };

        Type type = Type::Null;
        bool bool_value = false;
        double number_value = 0.0;
        std::string string_value;
        std::vector<JsonValue> array_value;
        std::map<std::string, JsonValue> object_value;

        bool IsNull() const { return type == Type::Null; }
        bool IsBool() const { return type == Type::Bool; }
        bool IsNumber() const { return type == Type::Number; }
        bool IsString() const { return type == Type::String; }
        bool IsArray() const { return type == Type::Array; }
        bool IsObject() const { return type == Type::Object; }

        const JsonValue& operator[](const std::string& key) const;
        const JsonValue& At(size_t index) const;
        bool Has(const std::string& key) const;

        std::string AsString(const std::string& fallback = "") const;
        int AsInt(int fallback = 0) const;
        double AsDouble(double fallback = 0.0) const;
        bool AsBool(bool fallback = false) const;
    };

    struct JsonParseResult
    {
        bool ok = false;
        JsonValue value;
        std::string error;
    };

    JsonParseResult ParseJson(const std::string& text);
}
