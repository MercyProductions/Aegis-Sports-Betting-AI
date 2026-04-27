#include "Platform.h"
#include "SportsData.h"
#include "Json.h"
#include "AppVersion.h"

#include "imgui.h"
#include "imgui_impl_dx11.h"
#include "imgui_impl_win32.h"

#include <d3d11.h>
#include <dwmapi.h>
#include <tchar.h>
#include <windows.h>
#include <windowsx.h>

#include <algorithm>
#include <cctype>
#include <cfloat>
#include <chrono>
#include <cmath>
#include <cstdio>
#include <cstdlib>
#include <ctime>
#include <cstring>
#include <filesystem>
#include <fstream>
#include <future>
#include <iomanip>
#include <map>
#include <set>
#include <sstream>
#include <vector>

namespace
{
    ID3D11Device* g_pd3dDevice = nullptr;
    ID3D11DeviceContext* g_pd3dDeviceContext = nullptr;
    IDXGISwapChain* g_pSwapChain = nullptr;
    HWND g_AppHwnd = nullptr;
    bool g_SwapChainOccluded = false;
    UINT g_ResizeWidth = 0;
    UINT g_ResizeHeight = 0;
    ID3D11RenderTargetView* g_mainRenderTargetView = nullptr;

    bool CreateDeviceD3D(HWND hWnd);
    void CleanupDeviceD3D();
    void CreateRenderTarget();
    void CleanupRenderTarget();
    LRESULT WINAPI WndProc(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam);

    ImFont* g_font_regular = nullptr;
    ImFont* g_font_bold = nullptr;
    ImFont* g_font_title = nullptr;
    constexpr float kPi = 3.14159265358979323846f;
    constexpr int kMinWindowWidth = 1040;
    constexpr int kMinWindowHeight = 680;

#ifndef DWMWA_USE_IMMERSIVE_DARK_MODE
#define DWMWA_USE_IMMERSIVE_DARK_MODE 20
#endif

#ifndef DWMWA_WINDOW_CORNER_PREFERENCE
#define DWMWA_WINDOW_CORNER_PREFERENCE 33
#endif

#ifndef DWMWCP_ROUND
#define DWMWCP_ROUND 2
#endif

    ImU32 Col(float r, float g, float b, float a = 1.0f)
    {
        return ImGui::ColorConvertFloat4ToU32(ImVec4(r, g, b, a));
    }

    ImVec4 V4(float r, float g, float b, float a = 1.0f)
    {
        return ImVec4(r, g, b, a);
    }

    void TextMuted(const char* text)
    {
        ImGui::PushStyleColor(ImGuiCol_Text, V4(0.62f, 0.67f, 0.64f, 1.0f));
        ImGui::TextUnformatted(text);
        ImGui::PopStyleColor();
    }

    void TextGreen(const char* text)
    {
        ImGui::PushStyleColor(ImGuiCol_Text, V4(0.19f, 0.85f, 0.42f, 1.0f));
        ImGui::TextUnformatted(text);
        ImGui::PopStyleColor();
    }

    enum class IconKind
    {
        Dashboard,
        Live,
        Calendar,
        Bell,
        Scanner,
        Brain,
        Prop,
        Arbitrage,
        Report,
        Settings,
        Sportsbook,
        Exchange,
        Trophy,
        Chart,
        Shield,
        Search,
        Filter,
        Users,
        Lightning,
        Wallet,
        Ball,
        Basketball,
        Football,
        Baseball,
        Hockey,
        Soccer,
        Fight,
        Tennis,
        Esports,
        More
    };

    void DrawIcon(ImDrawList* draw, ImVec2 center, float size, IconKind icon, ImU32 color, float thickness = 1.8f);

    bool AegisButton(const char* label, const ImVec2& size, bool active = false)
    {
        ImGui::PushStyleVar(ImGuiStyleVar_FrameRounding, 8.0f);
        ImGui::PushStyleVar(ImGuiStyleVar_FrameBorderSize, 1.0f);
        ImGui::PushStyleColor(ImGuiCol_Button, active ? V4(0.09f, 0.23f, 0.14f, 1.0f) : V4(0.05f, 0.08f, 0.07f, 0.95f));
        ImGui::PushStyleColor(ImGuiCol_ButtonHovered, V4(0.12f, 0.27f, 0.17f, 1.0f));
        ImGui::PushStyleColor(ImGuiCol_ButtonActive, V4(0.10f, 0.33f, 0.18f, 1.0f));
        ImGui::PushStyleColor(ImGuiCol_Border, active ? V4(0.26f, 0.86f, 0.46f, 0.42f) : V4(0.77f, 0.87f, 0.81f, 0.13f));
        ImGui::PushStyleColor(ImGuiCol_Text, active ? V4(0.45f, 1.0f, 0.62f, 1.0f) : V4(0.83f, 0.88f, 0.85f, 1.0f));
        const bool clicked = ImGui::Button(label, size);
        ImGui::PopStyleColor(5);
        ImGui::PopStyleVar(2);
        return clicked;
    }

    bool AegisIconButton(const char* label, IconKind icon, const ImVec2& size, bool active = false)
    {
        ImDrawList* draw = ImGui::GetWindowDrawList();
        const ImVec2 pos = ImGui::GetCursorScreenPos();
        ImGui::PushID(label);
        const bool clicked = ImGui::InvisibleButton("icon_button", size);
        const bool hovered = ImGui::IsItemHovered();
        const ImU32 bg = active ? Col(0.09f, 0.25f, 0.13f, 1.0f) : hovered ? Col(0.07f, 0.14f, 0.10f, 0.98f) : Col(0.04f, 0.07f, 0.06f, 0.94f);
        const ImU32 border = active ? Col(0.26f, 0.86f, 0.46f, 0.55f) : Col(0.77f, 0.87f, 0.81f, 0.14f);
        const ImU32 fg = active ? Col(0.45f, 1.0f, 0.62f, 1.0f) : Col(0.78f, 0.84f, 0.80f, 1.0f);
        draw->AddRectFilled(pos, ImVec2(pos.x + size.x, pos.y + size.y), bg, 8.0f);
        draw->AddRect(pos, ImVec2(pos.x + size.x, pos.y + size.y), border, 8.0f);
        const ImVec2 text_size = ImGui::CalcTextSize(label);
        const float content_w = text_size.x + 25.0f;
        const float start_x = pos.x + (size.x - content_w) * 0.5f;
        DrawIcon(draw, ImVec2(start_x + 8.0f, pos.y + size.y * 0.5f), 16.0f, icon, fg, 1.45f);
        draw->AddText(g_font_bold, 14.0f, ImVec2(start_x + 24.0f, pos.y + (size.y - text_size.y) * 0.5f), fg, label);
        ImGui::PopID();
        return clicked;
    }

    bool PlainLinkButton(const char* label)
    {
        ImGui::PushStyleColor(ImGuiCol_Button, V4(0, 0, 0, 0));
        ImGui::PushStyleColor(ImGuiCol_ButtonHovered, V4(0.10f, 0.18f, 0.13f, 0.75f));
        ImGui::PushStyleColor(ImGuiCol_ButtonActive, V4(0.09f, 0.25f, 0.14f, 0.90f));
        ImGui::PushStyleColor(ImGuiCol_Text, V4(0.75f, 0.82f, 0.78f, 1.0f));
        const bool clicked = ImGui::Button(label);
        ImGui::PopStyleColor(4);
        return clicked;
    }

    void DrawLogo(ImDrawList* draw, ImVec2 center, float size, ImU32 color)
    {
        const float r = size * 0.5f;
        ImVec2 points[6] = {
            ImVec2(center.x, center.y - r),
            ImVec2(center.x + r * 0.78f, center.y - r * 0.48f),
            ImVec2(center.x + r * 0.64f, center.y + r * 0.54f),
            ImVec2(center.x, center.y + r),
            ImVec2(center.x - r * 0.64f, center.y + r * 0.54f),
            ImVec2(center.x - r * 0.78f, center.y - r * 0.48f),
        };
        draw->AddPolyline(points, 6, color, ImDrawFlags_Closed, 2.4f);
        draw->AddLine(ImVec2(center.x - r * 0.28f, center.y + r * 0.42f), ImVec2(center.x, center.y - r * 0.34f), color, 2.4f);
        draw->AddLine(ImVec2(center.x + r * 0.28f, center.y + r * 0.42f), ImVec2(center.x, center.y - r * 0.34f), color, 2.4f);
        draw->AddLine(ImVec2(center.x - r * 0.13f, center.y + r * 0.08f), ImVec2(center.x + r * 0.13f, center.y + r * 0.08f), color, 2.0f);
    }

    void DrawArc(ImDrawList* draw, ImVec2 center, float radius, float a_min, float a_max, ImU32 color, float thickness)
    {
        draw->PathArcTo(center, radius, a_min, a_max, 24);
        draw->PathStroke(color, 0, thickness);
    }

    void DrawCorner(ImDrawList* draw, ImVec2 outer, ImVec2 inner, ImU32 color, float thickness)
    {
        draw->AddLine(outer, ImVec2(inner.x, outer.y), color, thickness);
        draw->AddLine(outer, ImVec2(outer.x, inner.y), color, thickness);
    }

    void DrawIcon(ImDrawList* draw, ImVec2 center, float size, IconKind icon, ImU32 color, float thickness)
    {
        const float r = size * 0.5f;
        switch (icon)
        {
        case IconKind::Dashboard:
            DrawArc(draw, ImVec2(center.x, center.y + r * 0.20f), r * 0.78f, kPi * 1.02f, kPi * 1.98f, color, thickness);
            draw->AddLine(center, ImVec2(center.x + r * 0.44f, center.y - r * 0.34f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.58f, center.y + r * 0.44f), ImVec2(center.x + r * 0.58f, center.y + r * 0.44f), color, thickness);
            break;
        case IconKind::Live:
            draw->AddCircle(center, r * 0.22f, color, 24, thickness);
            DrawArc(draw, center, r * 0.54f, -0.70f, 0.70f, color, thickness);
            DrawArc(draw, center, r * 0.54f, kPi - 0.70f, kPi + 0.70f, color, thickness);
            DrawArc(draw, center, r * 0.82f, -0.62f, 0.62f, color, thickness);
            DrawArc(draw, center, r * 0.82f, kPi - 0.62f, kPi + 0.62f, color, thickness);
            break;
        case IconKind::Calendar:
            draw->AddRect(ImVec2(center.x - r * 0.64f, center.y - r * 0.46f), ImVec2(center.x + r * 0.64f, center.y + r * 0.64f), color, 3.0f, 0, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.64f, center.y - r * 0.16f), ImVec2(center.x + r * 0.64f, center.y - r * 0.16f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.34f, center.y - r * 0.72f), ImVec2(center.x - r * 0.34f, center.y - r * 0.30f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.34f, center.y - r * 0.72f), ImVec2(center.x + r * 0.34f, center.y - r * 0.30f), color, thickness);
            break;
        case IconKind::Bell:
            DrawArc(draw, ImVec2(center.x, center.y + r * 0.03f), r * 0.50f, kPi * 1.08f, kPi * 1.92f, color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.50f, center.y), ImVec2(center.x - r * 0.60f, center.y + r * 0.46f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.50f, center.y), ImVec2(center.x + r * 0.60f, center.y + r * 0.46f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.66f, center.y + r * 0.46f), ImVec2(center.x + r * 0.66f, center.y + r * 0.46f), color, thickness);
            draw->AddCircleFilled(ImVec2(center.x, center.y + r * 0.64f), r * 0.10f, color);
            break;
        case IconKind::Scanner:
            DrawCorner(draw, ImVec2(center.x - r * 0.72f, center.y - r * 0.72f), ImVec2(center.x - r * 0.26f, center.y - r * 0.26f), color, thickness);
            DrawCorner(draw, ImVec2(center.x + r * 0.72f, center.y - r * 0.72f), ImVec2(center.x + r * 0.26f, center.y - r * 0.26f), color, thickness);
            DrawCorner(draw, ImVec2(center.x - r * 0.72f, center.y + r * 0.72f), ImVec2(center.x - r * 0.26f, center.y + r * 0.26f), color, thickness);
            DrawCorner(draw, ImVec2(center.x + r * 0.72f, center.y + r * 0.72f), ImVec2(center.x + r * 0.26f, center.y + r * 0.26f), color, thickness);
            draw->AddCircle(center, r * 0.24f, color, 24, thickness);
            break;
        case IconKind::Brain:
            draw->AddBezierCubic(ImVec2(center.x, center.y - r * 0.64f), ImVec2(center.x - r * 0.70f, center.y - r * 0.62f), ImVec2(center.x - r * 0.70f, center.y + r * 0.50f), ImVec2(center.x, center.y + r * 0.56f), color, thickness);
            draw->AddBezierCubic(ImVec2(center.x, center.y - r * 0.64f), ImVec2(center.x + r * 0.70f, center.y - r * 0.62f), ImVec2(center.x + r * 0.70f, center.y + r * 0.50f), ImVec2(center.x, center.y + r * 0.56f), color, thickness);
            draw->AddLine(ImVec2(center.x, center.y - r * 0.56f), ImVec2(center.x, center.y + r * 0.58f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y - r * 0.10f), ImVec2(center.x - r * 0.08f, center.y - r * 0.10f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.08f, center.y + r * 0.14f), ImVec2(center.x + r * 0.42f, center.y + r * 0.14f), color, thickness);
            break;
        case IconKind::Prop:
        case IconKind::Report:
            draw->AddRect(ImVec2(center.x - r * 0.48f, center.y - r * 0.70f), ImVec2(center.x + r * 0.48f, center.y + r * 0.70f), color, 3.0f, 0, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.25f, center.y - r * 0.22f), ImVec2(center.x + r * 0.25f, center.y - r * 0.22f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.25f, center.y + r * 0.05f), ImVec2(center.x + r * 0.20f, center.y + r * 0.05f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.25f, center.y + r * 0.32f), ImVec2(center.x + r * 0.32f, center.y + r * 0.32f), color, thickness);
            break;
        case IconKind::Arbitrage:
            draw->AddLine(ImVec2(center.x - r * 0.62f, center.y - r * 0.36f), ImVec2(center.x + r * 0.46f, center.y - r * 0.36f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.46f, center.y - r * 0.36f), ImVec2(center.x + r * 0.18f, center.y - r * 0.62f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.46f, center.y - r * 0.36f), ImVec2(center.x + r * 0.18f, center.y - r * 0.10f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.62f, center.y + r * 0.36f), ImVec2(center.x - r * 0.46f, center.y + r * 0.36f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.46f, center.y + r * 0.36f), ImVec2(center.x - r * 0.18f, center.y + r * 0.62f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.46f, center.y + r * 0.36f), ImVec2(center.x - r * 0.18f, center.y + r * 0.10f), color, thickness);
            break;
        case IconKind::Settings:
            for (int i = 0; i < 8; ++i)
            {
                const float a = static_cast<float>(i) * kPi * 0.25f;
                draw->AddLine(ImVec2(center.x + std::cos(a) * r * 0.54f, center.y + std::sin(a) * r * 0.54f), ImVec2(center.x + std::cos(a) * r * 0.78f, center.y + std::sin(a) * r * 0.78f), color, thickness);
            }
            draw->AddCircle(center, r * 0.42f, color, 32, thickness);
            draw->AddCircle(center, r * 0.16f, color, 20, thickness);
            break;
        case IconKind::Sportsbook:
            draw->AddRect(ImVec2(center.x - r * 0.66f, center.y - r * 0.48f), ImVec2(center.x + r * 0.66f, center.y + r * 0.48f), color, 4.0f, 0, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.36f, center.y), ImVec2(center.x + r * 0.36f, center.y), color, thickness);
            draw->AddCircle(center, r * 0.16f, color, 18, thickness);
            break;
        case IconKind::Exchange:
            draw->AddLine(ImVec2(center.x - r * 0.58f, center.y - r * 0.20f), ImVec2(center.x + r * 0.42f, center.y - r * 0.20f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.42f, center.y - r * 0.20f), ImVec2(center.x + r * 0.20f, center.y - r * 0.44f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.42f, center.y - r * 0.20f), ImVec2(center.x + r * 0.20f, center.y + r * 0.04f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.58f, center.y + r * 0.24f), ImVec2(center.x - r * 0.42f, center.y + r * 0.24f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y + r * 0.24f), ImVec2(center.x - r * 0.20f, center.y), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y + r * 0.24f), ImVec2(center.x - r * 0.20f, center.y + r * 0.48f), color, thickness);
            break;
        case IconKind::Trophy:
            draw->AddRect(ImVec2(center.x - r * 0.34f, center.y - r * 0.54f), ImVec2(center.x + r * 0.34f, center.y + r * 0.08f), color, 2.0f, 0, thickness);
            DrawArc(draw, ImVec2(center.x - r * 0.50f, center.y - r * 0.28f), r * 0.24f, kPi * 0.50f, kPi * 1.50f, color, thickness);
            DrawArc(draw, ImVec2(center.x + r * 0.50f, center.y - r * 0.28f), r * 0.24f, -kPi * 0.50f, kPi * 0.50f, color, thickness);
            draw->AddLine(ImVec2(center.x, center.y + r * 0.08f), ImVec2(center.x, center.y + r * 0.46f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y + r * 0.62f), ImVec2(center.x + r * 0.42f, center.y + r * 0.62f), color, thickness);
            break;
        case IconKind::Chart:
            draw->AddLine(ImVec2(center.x - r * 0.66f, center.y + r * 0.48f), ImVec2(center.x - r * 0.16f, center.y + r * 0.02f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.16f, center.y + r * 0.02f), ImVec2(center.x + r * 0.10f, center.y + r * 0.20f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.10f, center.y + r * 0.20f), ImVec2(center.x + r * 0.62f, center.y - r * 0.46f), color, thickness);
            break;
        case IconKind::Shield:
            DrawLogo(draw, center, size, color);
            break;
        case IconKind::Search:
            draw->AddCircle(ImVec2(center.x - r * 0.10f, center.y - r * 0.10f), r * 0.44f, color, 28, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.24f, center.y + r * 0.24f), ImVec2(center.x + r * 0.62f, center.y + r * 0.62f), color, thickness);
            break;
        case IconKind::Filter:
            draw->AddLine(ImVec2(center.x - r * 0.64f, center.y - r * 0.44f), ImVec2(center.x + r * 0.64f, center.y - r * 0.44f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.38f, center.y), ImVec2(center.x + r * 0.38f, center.y), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.16f, center.y + r * 0.44f), ImVec2(center.x + r * 0.16f, center.y + r * 0.44f), color, thickness);
            break;
        case IconKind::Users:
            draw->AddCircle(ImVec2(center.x - r * 0.20f, center.y - r * 0.18f), r * 0.22f, color, 20, thickness);
            draw->AddCircle(ImVec2(center.x + r * 0.30f, center.y - r * 0.06f), r * 0.18f, color, 20, thickness);
            DrawArc(draw, ImVec2(center.x - r * 0.20f, center.y + r * 0.54f), r * 0.44f, kPi * 1.08f, kPi * 1.92f, color, thickness);
            DrawArc(draw, ImVec2(center.x + r * 0.30f, center.y + r * 0.52f), r * 0.32f, kPi * 1.10f, kPi * 1.90f, color, thickness);
            break;
        case IconKind::Lightning:
            draw->AddLine(ImVec2(center.x + r * 0.10f, center.y - r * 0.70f), ImVec2(center.x - r * 0.34f, center.y + r * 0.05f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.34f, center.y + r * 0.05f), ImVec2(center.x + r * 0.08f, center.y + r * 0.05f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.08f, center.y + r * 0.05f), ImVec2(center.x - r * 0.12f, center.y + r * 0.70f), color, thickness);
            break;
        case IconKind::Wallet:
            draw->AddRect(ImVec2(center.x - r * 0.66f, center.y - r * 0.42f), ImVec2(center.x + r * 0.66f, center.y + r * 0.50f), color, 4.0f, 0, thickness);
            draw->AddRect(ImVec2(center.x + r * 0.16f, center.y - r * 0.10f), ImVec2(center.x + r * 0.68f, center.y + r * 0.22f), color, 3.0f, 0, thickness);
            draw->AddCircleFilled(ImVec2(center.x + r * 0.42f, center.y + r * 0.06f), r * 0.05f, color);
            break;
        case IconKind::Ball:
            draw->AddCircle(center, r * 0.68f, color, 36, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.68f, center.y), ImVec2(center.x + r * 0.68f, center.y), color, thickness);
            DrawArc(draw, center, r * 0.68f, kPi * 0.50f, kPi * 1.50f, color, thickness);
            DrawArc(draw, center, r * 0.68f, -kPi * 0.50f, kPi * 0.50f, color, thickness);
            break;
        case IconKind::Basketball:
            draw->AddCircle(center, r * 0.68f, color, 36, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.68f, center.y), ImVec2(center.x + r * 0.68f, center.y), color, thickness);
            draw->AddLine(ImVec2(center.x, center.y - r * 0.68f), ImVec2(center.x, center.y + r * 0.68f), color, thickness);
            DrawArc(draw, ImVec2(center.x - r * 0.50f, center.y), r * 0.68f, -0.95f, 0.95f, color, thickness);
            DrawArc(draw, ImVec2(center.x + r * 0.50f, center.y), r * 0.68f, kPi - 0.95f, kPi + 0.95f, color, thickness);
            break;
        case IconKind::Football:
            draw->AddBezierCubic(ImVec2(center.x - r * 0.78f, center.y), ImVec2(center.x - r * 0.30f, center.y - r * 0.70f), ImVec2(center.x + r * 0.30f, center.y - r * 0.70f), ImVec2(center.x + r * 0.78f, center.y), color, thickness);
            draw->AddBezierCubic(ImVec2(center.x - r * 0.78f, center.y), ImVec2(center.x - r * 0.30f, center.y + r * 0.70f), ImVec2(center.x + r * 0.30f, center.y + r * 0.70f), ImVec2(center.x + r * 0.78f, center.y), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.26f, center.y), ImVec2(center.x + r * 0.26f, center.y), color, thickness);
            for (int i = -1; i <= 1; ++i)
                draw->AddLine(ImVec2(center.x + i * r * 0.16f, center.y - r * 0.12f), ImVec2(center.x + i * r * 0.16f, center.y + r * 0.12f), color, thickness);
            break;
        case IconKind::Baseball:
            draw->AddCircle(center, r * 0.68f, color, 36, thickness);
            DrawArc(draw, ImVec2(center.x - r * 0.52f, center.y), r * 0.52f, -0.95f, 0.95f, color, thickness);
            DrawArc(draw, ImVec2(center.x + r * 0.52f, center.y), r * 0.52f, kPi - 0.95f, kPi + 0.95f, color, thickness);
            break;
        case IconKind::Hockey:
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y - r * 0.70f), ImVec2(center.x - r * 0.05f, center.y + r * 0.40f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.05f, center.y + r * 0.40f), ImVec2(center.x + r * 0.64f, center.y + r * 0.36f), color, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.36f, center.y - r * 0.62f), ImVec2(center.x + r * 0.06f, center.y + r * 0.28f), color, thickness);
            draw->AddEllipse(ImVec2(center.x + r * 0.48f, center.y + r * 0.58f), ImVec2(r * 0.30f, r * 0.10f), color, 0.0f, 24, thickness);
            break;
        case IconKind::Soccer:
            draw->AddCircle(center, r * 0.68f, color, 36, thickness);
            draw->AddCircle(center, r * 0.20f, color, 5, thickness);
            for (int i = 0; i < 5; ++i)
            {
                const float a = -kPi * 0.5f + static_cast<float>(i) * kPi * 0.4f;
                draw->AddLine(center, ImVec2(center.x + std::cos(a) * r * 0.58f, center.y + std::sin(a) * r * 0.58f), color, thickness);
            }
            break;
        case IconKind::Fight:
            draw->AddRect(ImVec2(center.x - r * 0.62f, center.y - r * 0.20f), ImVec2(center.x + r * 0.26f, center.y + r * 0.46f), color, 5.0f, 0, thickness);
            draw->AddRect(ImVec2(center.x + r * 0.18f, center.y - r * 0.50f), ImVec2(center.x + r * 0.58f, center.y + r * 0.16f), color, 5.0f, 0, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.42f, center.y - r * 0.20f), ImVec2(center.x - r * 0.42f, center.y + r * 0.30f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.14f, center.y - r * 0.20f), ImVec2(center.x - r * 0.14f, center.y + r * 0.30f), color, thickness);
            break;
        case IconKind::Tennis:
            draw->AddCircle(ImVec2(center.x - r * 0.10f, center.y - r * 0.12f), r * 0.42f, color, 28, thickness);
            draw->AddLine(ImVec2(center.x + r * 0.20f, center.y + r * 0.20f), ImVec2(center.x + r * 0.62f, center.y + r * 0.62f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.36f, center.y - r * 0.12f), ImVec2(center.x + r * 0.16f, center.y - r * 0.12f), color, thickness * 0.72f);
            draw->AddLine(ImVec2(center.x - r * 0.10f, center.y - r * 0.38f), ImVec2(center.x - r * 0.10f, center.y + r * 0.14f), color, thickness * 0.72f);
            break;
        case IconKind::Esports:
            draw->AddRect(ImVec2(center.x - r * 0.70f, center.y - r * 0.34f), ImVec2(center.x + r * 0.70f, center.y + r * 0.40f), color, 8.0f, 0, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.44f, center.y + r * 0.02f), ImVec2(center.x - r * 0.18f, center.y + r * 0.02f), color, thickness);
            draw->AddLine(ImVec2(center.x - r * 0.31f, center.y - r * 0.12f), ImVec2(center.x - r * 0.31f, center.y + r * 0.16f), color, thickness);
            draw->AddCircle(ImVec2(center.x + r * 0.30f, center.y - r * 0.02f), r * 0.08f, color, 12, thickness);
            draw->AddCircle(ImVec2(center.x + r * 0.50f, center.y + r * 0.12f), r * 0.08f, color, 12, thickness);
            break;
        case IconKind::More:
            draw->AddCircleFilled(ImVec2(center.x - r * 0.36f, center.y), r * 0.08f, color);
            draw->AddCircleFilled(center, r * 0.08f, color);
            draw->AddCircleFilled(ImVec2(center.x + r * 0.36f, center.y), r * 0.08f, color);
            break;
        }
    }

    void DrawIconBadge(ImDrawList* draw, ImVec2 min, ImVec2 size, IconKind icon, bool active)
    {
        const ImU32 bg = active ? Col(0.09f, 0.25f, 0.13f, 1.0f) : Col(0.10f, 0.16f, 0.13f, 1.0f);
        const ImU32 border = active ? Col(0.35f, 1.0f, 0.55f, 0.34f) : Col(0.77f, 0.87f, 0.81f, 0.08f);
        const ImU32 fg = active ? Col(0.35f, 1.0f, 0.55f, 1.0f) : Col(0.62f, 0.67f, 0.64f, 1.0f);
        draw->AddRectFilled(min, ImVec2(min.x + size.x, min.y + size.y), bg, 7.0f);
        draw->AddRect(min, ImVec2(min.x + size.x, min.y + size.y), border, 7.0f);
        DrawIcon(draw, ImVec2(min.x + size.x * 0.5f, min.y + size.y * 0.5f), std::min(size.x, size.y) * 0.66f, icon, fg, 1.6f);
    }

    unsigned int HashLabel(const std::string& label)
    {
        unsigned int hash = 2166136261u;
        for (const unsigned char ch : label)
        {
            hash ^= static_cast<unsigned int>(std::tolower(ch));
            hash *= 16777619u;
        }
        return hash;
    }

    ImVec4 LabelAccent(const std::string& label, float alpha)
    {
        const ImVec4 palette[] = {
            V4(0.19f, 0.85f, 0.42f, alpha),
            V4(0.18f, 0.55f, 0.96f, alpha),
            V4(0.93f, 0.66f, 0.20f, alpha),
            V4(0.72f, 0.34f, 0.94f, alpha),
            V4(0.09f, 0.78f, 0.75f, alpha),
            V4(0.96f, 0.34f, 0.42f, alpha)
        };
        return palette[HashLabel(label) % IM_ARRAYSIZE(palette)];
    }

    ImU32 LabelAccentU32(const std::string& label, float alpha)
    {
        return ImGui::ColorConvertFloat4ToU32(LabelAccent(label, alpha));
    }

    void DrawGeneratedLogoBadge(ImDrawList* draw, ImVec2 min, ImVec2 size, const std::string& label, IconKind icon, bool active, bool show_initials = true)
    {
        const float min_dim = std::min(size.x, size.y);
        const ImVec2 max(min.x + size.x, min.y + size.y);
        const ImVec2 center(min.x + size.x * 0.5f, min.y + size.y * 0.5f);
        const float rounding = std::max(7.0f, min_dim * 0.28f);
        const std::string seed = label.empty() ? "Aegis" : label;
        const ImU32 bg = active ? LabelAccentU32(seed, 0.18f) : Col(0.045f, 0.075f, 0.062f, 0.96f);
        const ImU32 halo = LabelAccentU32(seed, active ? 0.24f : 0.14f);
        const ImU32 border = LabelAccentU32(seed, active ? 0.58f : 0.28f);

        draw->AddRectFilled(min, max, bg, rounding);
        draw->AddRectFilled(ImVec2(min.x + 2.0f, min.y + 2.0f), ImVec2(max.x - 2.0f, max.y - 2.0f), Col(0.015f, 0.027f, 0.022f, active ? 0.54f : 0.68f), std::max(5.0f, rounding - 2.0f));
        draw->AddCircleFilled(center, min_dim * 0.38f, halo);
        DrawIcon(draw, center, min_dim * 0.58f, icon, LabelAccentU32(seed, show_initials ? 0.34f : 0.95f), std::max(1.15f, min_dim * 0.055f));

        if (show_initials)
        {
            std::string initials;
            bool compact_code = seed.size() <= 4;
            for (const unsigned char ch : seed)
            {
                if (!std::isalnum(ch))
                {
                    compact_code = false;
                    break;
                }
            }
            if (compact_code)
            {
                for (const unsigned char ch : seed)
                    initials.push_back(static_cast<char>(std::toupper(ch)));
            }
            else
            {
                initials = aegis::Initials(seed);
            }
            if (initials.empty())
                initials = "A";
            if (initials.size() > 2)
                initials.resize(2);
            const float font_size = min_dim <= 28.0f ? 10.0f : min_dim <= 38.0f ? 12.0f : 15.0f;
            ImFont* font = g_font_bold ? g_font_bold : ImGui::GetFont();
            const ImVec2 text_size = font->CalcTextSizeA(font_size, FLT_MAX, 0.0f, initials.c_str());
            draw->AddText(font, font_size, ImVec2(center.x - text_size.x * 0.5f, center.y - text_size.y * 0.5f - 0.5f), Col(0.96f, 1.0f, 0.97f, 1.0f), initials.c_str());
        }

        draw->AddRect(min, max, border, rounding, 0, 1.2f);
        draw->AddLine(ImVec2(min.x + min_dim * 0.24f, min.y + 1.0f), ImVec2(max.x - min_dim * 0.24f, min.y + 1.0f), LabelAccentU32(seed, 0.30f), 1.0f);
    }

    enum class ChromeButtonKind
    {
        Minimize,
        Maximize,
        Close
    };

    void ConfigureBorderlessWindow(HWND hwnd)
    {
        BOOL dark = TRUE;
        DwmSetWindowAttribute(hwnd, DWMWA_USE_IMMERSIVE_DARK_MODE, &dark, sizeof(dark));

        int corner = DWMWCP_ROUND;
        DwmSetWindowAttribute(hwnd, DWMWA_WINDOW_CORNER_PREFERENCE, &corner, sizeof(corner));
    }

    void BeginNativeDragZone(const char* id, ImVec2 pos, ImVec2 size)
    {
        if (g_AppHwnd == nullptr || size.x <= 0.0f || size.y <= 0.0f)
            return;

        ImGui::SetCursorScreenPos(pos);
        ImGui::InvisibleButton(id, size);
        if (ImGui::IsItemHovered() && ImGui::IsMouseClicked(ImGuiMouseButton_Left))
        {
            ReleaseCapture();
            SendMessageW(g_AppHwnd, WM_NCLBUTTONDOWN, HTCAPTION, 0);
        }
    }

    bool WindowChromeButton(const char* id, ImVec2 pos, ChromeButtonKind kind)
    {
        ImDrawList* draw = ImGui::GetWindowDrawList();
        const ImVec2 size(36.0f, 34.0f);
        ImGui::SetCursorScreenPos(pos);
        ImGui::PushID(id);
        const bool clicked = ImGui::InvisibleButton("chrome_hit", size);
        const bool hovered = ImGui::IsItemHovered();
        const bool active = ImGui::IsItemActive();
        ImGui::PopID();

        const bool close = kind == ChromeButtonKind::Close;
        const ImU32 bg = hovered ? (close ? Col(0.56f, 0.11f, 0.15f, 0.92f) : Col(0.08f, 0.16f, 0.11f, 0.94f)) : Col(0.04f, 0.07f, 0.06f, 0.70f);
        const ImU32 border = hovered ? (close ? Col(1.0f, 0.34f, 0.40f, 0.55f) : Col(0.35f, 1.0f, 0.55f, 0.28f)) : Col(0.77f, 0.87f, 0.81f, 0.12f);
        const ImU32 fg = close && hovered ? Col(1.0f, 0.94f, 0.94f, 1.0f) : Col(0.82f, 0.90f, 0.86f, active ? 0.72f : 0.92f);
        const ImVec2 max(pos.x + size.x, pos.y + size.y);
        draw->AddRectFilled(pos, max, bg, 9.0f);
        draw->AddRect(pos, max, border, 9.0f, 0, hovered ? 1.25f : 1.0f);

        const ImVec2 c(pos.x + size.x * 0.5f, pos.y + size.y * 0.5f);
        if (kind == ChromeButtonKind::Minimize)
        {
            draw->AddLine(ImVec2(c.x - 7.0f, c.y + 5.0f), ImVec2(c.x + 7.0f, c.y + 5.0f), fg, 1.7f);
        }
        else if (kind == ChromeButtonKind::Maximize)
        {
            if (g_AppHwnd != nullptr && IsZoomed(g_AppHwnd))
            {
                draw->AddRect(ImVec2(c.x - 5.0f, c.y - 4.0f), ImVec2(c.x + 5.0f, c.y + 6.0f), fg, 1.0f, 0, 1.4f);
                draw->AddRect(ImVec2(c.x - 2.0f, c.y - 7.0f), ImVec2(c.x + 8.0f, c.y + 3.0f), fg, 1.0f, 0, 1.2f);
            }
            else
            {
                draw->AddRect(ImVec2(c.x - 6.0f, c.y - 6.0f), ImVec2(c.x + 6.0f, c.y + 6.0f), fg, 1.0f, 0, 1.5f);
            }
        }
        else
        {
            draw->AddLine(ImVec2(c.x - 6.0f, c.y - 6.0f), ImVec2(c.x + 6.0f, c.y + 6.0f), fg, 1.7f);
            draw->AddLine(ImVec2(c.x + 6.0f, c.y - 6.0f), ImVec2(c.x - 6.0f, c.y + 6.0f), fg, 1.7f);
        }
        return clicked;
    }

    void RenderNativeWindowChrome(ImVec2 size)
    {
        if (g_AppHwnd == nullptr)
            return;

        ImDrawList* draw = ImGui::GetWindowDrawList();
        const ImVec2 origin = ImGui::GetWindowPos();
        draw->AddRect(ImVec2(origin.x + 0.5f, origin.y + 0.5f), ImVec2(origin.x + size.x - 0.5f, origin.y + size.y - 0.5f), Col(0.35f, 1.0f, 0.55f, 0.14f), 0.0f, 0, 1.0f);

        BeginNativeDragZone("chrome_drag_top_strip", ImVec2(origin.x + 8.0f, origin.y), ImVec2(std::max(0.0f, size.x - 156.0f), 10.0f));
        BeginNativeDragZone("chrome_drag_brand", ImVec2(origin.x, origin.y + 10.0f), ImVec2(std::min(278.0f, std::max(0.0f, size.x - 156.0f)), 60.0f));

        const float y = origin.y + 13.0f;
        const float x = origin.x + size.x - 124.0f;
        if (WindowChromeButton("minimize", ImVec2(x, y), ChromeButtonKind::Minimize))
            ShowWindow(g_AppHwnd, SW_MINIMIZE);
        if (WindowChromeButton("maximize", ImVec2(x + 42.0f, y), ChromeButtonKind::Maximize))
            ShowWindow(g_AppHwnd, IsZoomed(g_AppHwnd) ? SW_RESTORE : SW_MAXIMIZE);
        if (WindowChromeButton("close", ImVec2(x + 84.0f, y), ChromeButtonKind::Close))
            PostMessageW(g_AppHwnd, WM_CLOSE, 0, 0);
    }

    ImVec2 CurrentClientSize(ImVec2 fallback)
    {
        if (g_AppHwnd == nullptr)
            return fallback;

        RECT rect{};
        if (!GetClientRect(g_AppHwnd, &rect))
            return fallback;

        const float width = static_cast<float>(std::max(1L, rect.right - rect.left));
        const float height = static_cast<float>(std::max(1L, rect.bottom - rect.top));
        return ImVec2(width, height);
    }

    void DrawSparkChart(const std::vector<float>& points, ImVec2 size, ImU32 line_color, const char* label, const char* value)
    {
        ImGui::BeginChild(label, size, true, ImGuiWindowFlags_NoScrollbar);
        ImDrawList* draw = ImGui::GetWindowDrawList();
        const ImVec2 pos = ImGui::GetCursorScreenPos();
        const ImVec2 min = pos;
        const ImVec2 max = ImVec2(pos.x + size.x - 16.0f, pos.y + size.y - 18.0f);
        draw->AddRectFilled(min, max, Col(0.04f, 0.08f, 0.06f, 0.72f), 8.0f);
        draw->AddRect(min, max, Col(1, 1, 1, 0.07f), 8.0f);
        ImGui::SetCursorScreenPos(ImVec2(min.x + 12, min.y + 10));
        TextMuted(label);
        ImGui::SameLine();
        TextGreen(value);

        const ImVec2 graph_min(min.x + 18.0f, min.y + 48.0f);
        const ImVec2 graph_max(max.x - 16.0f, max.y - 28.0f);
        for (int i = 0; i < 5; ++i)
        {
            const float y = graph_min.y + (graph_max.y - graph_min.y) * (static_cast<float>(i) / 4.0f);
            draw->AddLine(ImVec2(graph_min.x, y), ImVec2(graph_max.x, y), Col(1, 1, 1, 0.055f), 1.0f);
        }
        if (points.size() >= 2)
        {
            std::vector<ImVec2> out;
            out.reserve(points.size());
            for (size_t i = 0; i < points.size(); ++i)
            {
                const float x = graph_min.x + (graph_max.x - graph_min.x) * (static_cast<float>(i) / static_cast<float>(points.size() - 1));
                const float p = std::clamp(points[i], 0.0f, 100.0f);
                const float y = graph_max.y - (graph_max.y - graph_min.y) * (p / 100.0f);
                out.push_back(ImVec2(x, y));
            }
            draw->AddPolyline(out.data(), static_cast<int>(out.size()), line_color, 0, 2.2f);
            for (const ImVec2& p : out)
                draw->AddCircleFilled(p, 3.0f, line_color);
        }
        ImGui::Dummy(ImVec2(size.x - 22.0f, size.y - 24.0f));
        ImGui::EndChild();
    }

    enum class View
    {
        Setup,
        Health,
        Dashboard,
        Live,
        Picks,
        Details,
        Alerts,
        Analytics,
        Arbitrage,
        Props,
        Scenario,
        Watchlist,
        Exposure,
        Reports,
        Settings
    };

    struct RefreshResult
    {
        int request_id = 0;
        bool ok = false;
        aegis::SportsState state;
        std::string status;
        std::string diagnostic;
        std::string refresh_label;
        int elapsed_ms = 0;
        std::map<std::string, int> provider_latency_ms;
        std::vector<aegis::OptionalFeedValidationResult> optional_feeds;
    };

    class SportsApp
    {
    public:
        void Initialize()
        {
            config_ = aegis::LoadConfig();
            if (config_.migrated_config)
                status_ = "Config upgraded to schema v" + std::to_string(aegis::kConfigSchemaVersion) + ". Secrets stay in encrypted user storage.";
            state_ = aegis::MakeDemoSportsState();
            std::snprintf(odds_api_key_, sizeof(odds_api_key_), "%s", config_.odds_api_key.c_str());
            std::snprintf(kalshi_key_id_, sizeof(kalshi_key_id_), "%s", config_.kalshi_key_id.c_str());
            std::snprintf(kalshi_private_key_, sizeof(kalshi_private_key_), "%s", config_.kalshi_private_key.c_str());
            std::snprintf(favorite_teams_, sizeof(favorite_teams_), "%s", config_.favorite_teams.c_str());
            std::snprintf(favorite_leagues_, sizeof(favorite_leagues_), "%s", config_.favorite_leagues.c_str());
            std::snprintf(injury_feed_url_, sizeof(injury_feed_url_), "%s", config_.injury_feed_url.c_str());
            std::snprintf(lineup_feed_url_, sizeof(lineup_feed_url_), "%s", config_.lineup_feed_url.c_str());
            std::snprintf(news_feed_url_, sizeof(news_feed_url_), "%s", config_.news_feed_url.c_str());
            std::snprintf(props_feed_url_, sizeof(props_feed_url_), "%s", config_.props_feed_url.c_str());
            settings_refresh_seconds_ = config_.refresh_seconds;
            settings_tracked_games_ = config_.tracked_games;
            settings_model_count_ = config_.model_count;
            settings_bankroll_starting_amount_ = config_.bankroll_starting_amount;
            settings_max_ticket_amount_ = config_.max_ticket_amount;
            settings_daily_exposure_limit_ = config_.daily_exposure_limit;
            settings_min_ticket_confidence_ = config_.min_ticket_confidence;
            settings_paper_only_mode_ = config_.paper_only_mode;
            settings_require_live_confirmation_ = config_.require_live_confirmation;
            settings_notifications_enabled_ = config_.notifications_enabled;
            settings_bankroll_analytics_enabled_ = config_.bankroll_analytics_enabled;
            settings_player_props_enabled_ = config_.player_props_enabled;
            settings_responsible_use_accepted_ = config_.responsible_use_accepted;
            settings_legal_location_confirmed_ = config_.legal_location_confirmed;
            settings_alert_confidence_threshold_ = config_.alert_confidence_threshold;
            settings_alert_watchlist_only_ = config_.alert_watchlist_only;
            settings_alert_line_movement_only_ = config_.alert_line_movement_only;
            startup_integrity_ = BuildStartupIntegrityRows();
            LoadWatchlist();
            std::snprintf(login_user_, sizeof(login_user_), "%s", aegis::GetEnvUtf8(L"AEGIS_USERNAME").c_str());
            launcher_context_ = aegis::GetEnvUtf8(L"AEGIS_LAUNCHER_AUTH") == "1";
            aegis::AppendDiagnosticLine("startup launcher_context=" + std::string(launcher_context_ ? "1" : "0"));

            std::string launcher_cookie = aegis::GetEnvUtf8(L"AEGIS_AUTH_COOKIE");
            if (launcher_cookie.empty())
            {
                const std::string launcher_session = aegis::GetEnvUtf8(L"AEGIS_AUTH_SESSION");
                if (!launcher_session.empty())
                    launcher_cookie = "AEGIS_AUTH_SESSION=" + launcher_session;
            }
            if (!launcher_cookie.empty())
            {
                cookie_header_ = launcher_cookie;
                authenticated_ = true;
                username_ = login_user_[0] != '\0' ? login_user_ : "Aegis User";
                status_ = "Launcher session accepted.";
                aegis::AppendDiagnosticLine("launcher cookie accepted");
                BeginSportsRefresh(true, "Launcher session accepted", "Building the sports board from direct provider feeds.");
            }

            const aegis::RememberedCredentials remembered = aegis::LoadRememberedCredentials();
            aegis::AppendDiagnosticLine("remembered_credentials=" + std::string(remembered.ok ? "1" : "0"));
            if (remembered.ok)
            {
                std::snprintf(login_user_, sizeof(login_user_), "%s", remembered.username.c_str());
                std::snprintf(login_password_, sizeof(login_password_), "%s", remembered.password.c_str());
                remember_me_ = true;
                if (cookie_header_.empty())
                    AttemptLogin(false);
            }
            else if (launcher_context_ && login_user_[0] != '\0' && !authenticated_)
            {
                username_ = login_user_;
                status_ = "Launcher identity received. Sign in once here to unlock the native sports terminal.";
            }

            if (!authenticated_ && login_user_[0] == '\0')
                std::snprintf(login_user_, sizeof(login_user_), "%s", aegis::GetEnvUtf8(L"AEGIS_USER").c_str());

        }

        void EnableScreenshotSmoke(const std::string& view_name)
        {
            authenticated_ = true;
            initial_sync_ = false;
            refresh_in_flight_ = false;
            username_ = "ScreenshotSmoke";
            status_ = "Screenshot smoke mode: " + view_name;
            state_ = aegis::MakeDemoSportsState();
            last_refresh_label_ = "Screenshot smoke";
            if (!state_.predictions.empty())
            {
                selected_prediction_ = 0;
                selected_game_id_ = state_.predictions.front().game_id;
            }

            const std::string view = aegis::Lower(aegis::Trim(view_name));
            if (view == "setup") active_view_ = View::Setup;
            else if (view == "health") active_view_ = View::Health;
            else if (view == "live") active_view_ = View::Live;
            else if (view == "picks") active_view_ = View::Picks;
            else if (view == "reports") active_view_ = View::Reports;
            else if (view == "settings") active_view_ = View::Settings;
            else if (view == "watchlist") active_view_ = View::Watchlist;
            else if (view == "scenario") active_view_ = View::Scenario;
            else active_view_ = View::Dashboard;
        }

        void Render()
        {
            PollSportsRefresh();
            ImGuiIO& io = ImGui::GetIO();
            const ImVec2 frame_size = CurrentClientSize(io.DisplaySize);
            ImGui::SetNextWindowPos(ImVec2(0, 0), ImGuiCond_Always);
            ImGui::SetNextWindowSize(frame_size, ImGuiCond_Always);
            ImGui::PushStyleVar(ImGuiStyleVar_WindowPadding, ImVec2(0, 0));
            ImGui::Begin("Aegis Sports Betting AI Root", nullptr,
                ImGuiWindowFlags_NoTitleBar | ImGuiWindowFlags_NoResize | ImGuiWindowFlags_NoMove |
                ImGuiWindowFlags_NoScrollbar | ImGuiWindowFlags_NoSavedSettings | ImGuiWindowFlags_NoBringToFrontOnFocus);
            ImGui::PopStyleVar();

            DrawBackground(frame_size);
            if (!authenticated_)
                RenderLogin(frame_size);
            else if (initial_sync_ && refresh_in_flight_)
                RenderLoading(frame_size);
            else
                RenderShell(frame_size);

            RenderNativeWindowChrome(frame_size);
            ImGui::End();
        }

    private:
        struct AdapterProbe
        {
            bool checked = false;
            bool ok = false;
            bool reachable = false;
            int status_code = 0;
            int records = 0;
            int errors = 0;
            int warnings = 0;
            std::string contract;
            std::string status = "Not checked";
            std::string detail;
        };

        struct ProviderRefreshRecord
        {
            std::string key;
            std::string name;
            std::string status = "Waiting";
            std::string last_success = "--";
            std::string last_failure = "--";
            std::string latency = "--";
            std::string detail = "No refresh has been recorded for this provider in this session.";
        };

        aegis::Config config_;
        aegis::SportsState state_;
        bool authenticated_ = false;
        bool launcher_context_ = false;
        bool remember_me_ = true;
        std::string username_ = "Guest";
        std::string cookie_header_;
        std::string status_ = "Ready.";
        bool auth_offline_ = false;
        std::string last_refresh_label_ = "Not synced";
        char login_user_[128]{};
        char login_password_[128]{};
        char search_[160]{};
        char odds_api_key_[256]{};
        char kalshi_key_id_[256]{};
        char kalshi_private_key_[4096]{};
        char favorite_teams_[256]{};
        char favorite_leagues_[256]{};
        char injury_feed_url_[512]{};
        char lineup_feed_url_[512]{};
        char news_feed_url_[512]{};
        char props_feed_url_[512]{};
        int settings_refresh_seconds_ = 60;
        int settings_tracked_games_ = 100;
        int settings_model_count_ = 12;
        double settings_bankroll_starting_amount_ = 1000.0;
        double settings_max_ticket_amount_ = 250.0;
        double settings_daily_exposure_limit_ = 1000.0;
        int settings_min_ticket_confidence_ = 58;
        bool settings_paper_only_mode_ = true;
        bool settings_require_live_confirmation_ = true;
        bool settings_notifications_enabled_ = true;
        bool settings_bankroll_analytics_enabled_ = false;
        bool settings_player_props_enabled_ = false;
        bool settings_responsible_use_accepted_ = false;
        bool settings_legal_location_confirmed_ = false;
        int settings_alert_confidence_threshold_ = 65;
        bool settings_alert_watchlist_only_ = false;
        bool settings_alert_line_movement_only_ = false;
        double preview_amount_ = 10.0;
        double scenario_stake_ = 25.0;
        int scenario_probability_override_ = 0;
        bool live_order_preview_ = false;
        bool live_acknowledged_ = false;
        aegis::OddsValidationResult odds_validation_;
        std::string kalshi_status_ = "Not saved";
        std::string kalshi_detail_ = "Kalshi credentials are optional and stored encrypted when saved.";
        std::vector<std::string> watchlist_ids_;
        std::map<std::string, std::string> watch_notes_;
        std::map<std::string, AdapterProbe> adapter_probes_;
        std::map<std::string, ProviderRefreshRecord> provider_refresh_records_;
        std::vector<aegis::InfoItem> last_change_summary_;
        std::vector<aegis::InfoItem> startup_integrity_;
        std::string selected_game_id_;
        std::string export_status_;
        View active_view_ = View::Dashboard;
        std::string active_filter_ = "all";
        std::string provider_filter_ = "all";
        int report_sport_filter_ = 0;
        int report_date_filter_ = 0;
        int report_provider_filter_ = 0;
        int report_market_filter_ = 0;
        bool report_watchlist_only_ = false;
        char report_league_filter_[128]{};
        int confidence_filter_ = 50;
        bool filter_watchlist_only_ = false;
        bool filter_market_lines_only_ = false;
        bool filter_actionable_only_ = false;
        bool filter_favorites_only_ = false;
        int prediction_sort_mode_ = 0;
        std::set<std::string> seen_notification_keys_;
        int selected_prediction_ = -1;
        bool show_drawer_ = false;
        std::chrono::steady_clock::time_point last_refresh_ = std::chrono::steady_clock::now();
        std::future<RefreshResult> refresh_future_;
        bool refresh_in_flight_ = false;
        bool initial_sync_ = false;
        bool setup_guided_once_ = false;
        int refresh_request_id_ = 0;
        int last_refresh_elapsed_ms_ = 0;
        std::chrono::steady_clock::time_point sync_started_ = std::chrono::steady_clock::now();
        std::string sync_headline_ = "Preparing Aegis market intelligence";
        std::string sync_detail_ = "Authorizing your account and building the native sports board.";

        struct AuditRow
        {
            std::string time;
            std::string game_id;
            std::string matchup;
            std::string market;
            std::string pick;
            int confidence = 0;
            std::string actual;
            std::string result;
        };

        struct ExposureRow
        {
            std::string date;
            std::string mode;
            double amount = 0.0;
            std::string game_id;
            std::string matchup;
        };

        struct MarketSnapshotRow
        {
            std::string key;
            std::string matchup;
            std::string book;
            std::string line;
            std::string price;
            std::string seen_at;
        };

        struct DecisionJournalRow
        {
            std::string date;
            std::string time;
            std::string game_id;
            std::string matchup;
            std::string market;
            std::string pick;
            int confidence = 0;
            int probability = 0;
            double stake = 0.0;
            std::string provider;
            std::string line;
            std::string price;
            double expected_return = 0.0;
        };

        struct CalibrationBucket
        {
            std::string label;
            int samples = 0;
            int graded = 0;
            int wins = 0;
            int confidence_sum = 0;
        };

        struct ProviderQuality
        {
            std::string book;
            int live_lines = 0;
            int snapshots = 0;
            int moved = 0;
            int games = 0;
        };

        struct ScenarioLine
        {
            aegis::BetLink link;
            std::string source;
        };

        struct JournalLineComparison
        {
            bool found = false;
            bool same_provider = false;
            bool saved_priced = false;
            std::string provider;
            std::string line;
            std::string price;
            std::string scope;
            double current_expected = 0.0;
            double clv_delta = 0.0;
            double ev_delta = 0.0;
        };

        void DrawBackground(ImVec2 size)
        {
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 origin = ImGui::GetWindowPos();
            draw->AddRectFilled(origin, ImVec2(origin.x + size.x, origin.y + size.y), Col(0.012f, 0.022f, 0.023f, 1.0f));
            draw->AddRectFilledMultiColor(
                origin,
                ImVec2(origin.x + size.x, origin.y + 230.0f),
                Col(0.035f, 0.105f, 0.070f, 0.86f),
                Col(0.030f, 0.060f, 0.085f, 0.76f),
                Col(0.012f, 0.022f, 0.023f, 0.0f),
                Col(0.012f, 0.022f, 0.023f, 0.0f));
            draw->AddRectFilledMultiColor(
                ImVec2(origin.x, origin.y + size.y - 220.0f),
                ImVec2(origin.x + size.x, origin.y + size.y),
                Col(0.012f, 0.022f, 0.023f, 0.0f),
                Col(0.012f, 0.022f, 0.023f, 0.0f),
                Col(0.020f, 0.045f, 0.055f, 0.38f),
                Col(0.030f, 0.050f, 0.035f, 0.28f));
            for (float x = origin.x + 48.0f; x < origin.x + size.x; x += 96.0f)
                draw->AddLine(ImVec2(x, origin.y + 88.0f), ImVec2(x, origin.y + size.y), Col(0.77f, 0.87f, 0.81f, 0.018f), 1.0f);
            for (float y = origin.y + 114.0f; y < origin.y + size.y; y += 88.0f)
                draw->AddLine(ImVec2(origin.x, y), ImVec2(origin.x + size.x, y), Col(0.77f, 0.87f, 0.81f, 0.015f), 1.0f);
        }

        void RenderLogin(ImVec2 size)
        {
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 origin = ImGui::GetWindowPos();
            DrawLogo(draw, ImVec2(origin.x + 70.0f, origin.y + 70.0f), 36.0f, Col(0.19f, 0.85f, 0.42f, 1.0f));
            ImGui::SetCursorPos(ImVec2(104, 48));
            ImGui::PushFont(g_font_title);
            ImGui::TextUnformatted("AEGIS SPORTS");
            ImGui::PopFont();
            ImGui::SameLine();
            TextGreen("AI");
            ImGui::SetCursorPos(ImVec2(104, 76));
            TextMuted(AppVersionLabel().c_str());

            const float card_w = 520.0f;
            const float card_h = 500.0f;
            const ImVec2 card_pos((size.x - card_w) * 0.5f, (size.y - card_h) * 0.5f);
            ImGui::SetCursorPos(card_pos);
            ImGui::BeginChild("login_card", ImVec2(card_w, card_h), true, ImGuiWindowFlags_NoScrollbar);
            ImGui::Dummy(ImVec2(1, 24));
            ImGui::TextColored(V4(0.19f, 0.85f, 0.42f, 1.0f), "WELCOME BACK");
            ImGui::PushFont(g_font_title);
            ImGui::TextUnformatted("Sign in to Aegis Sports Betting AI");
            ImGui::PopFont();
            TextMuted("Use your Aegis account. Sports data is fetched directly by the desktop app from provider hosts.");
            ImGui::Spacing();
            StyledInputText("Username", "##login_user", login_user_, sizeof(login_user_));
            StyledInputText("Password", "##login_pass", login_password_, sizeof(login_password_), ImGuiInputTextFlags_Password);
            ImGui::Checkbox("Remember this account on this machine", &remember_me_);
            ImGui::Spacing();
            if (AegisButton("SIGN IN", ImVec2(-1, 48), true) || (ImGui::IsKeyPressed(ImGuiKey_Enter) && ImGui::IsWindowFocused(ImGuiFocusedFlags_RootAndChildWindows)))
                AttemptLogin(true);
            ImGui::Spacing();
            if (AegisButton("Open Website", ImVec2(-1, 42), false))
                aegis::OpenExternalUrl(aegis::JoinUrl(config_.auth_base_url, config_.website_path));
            if (!status_.empty())
            {
                ImGui::Spacing();
                ImGui::PushTextWrapPos(ImGui::GetCursorPosX() + card_w - 36.0f);
                const bool error_state = StatusLooksLikeError(status_);
                ImGui::TextColored(error_state ? V4(0.95f, 0.35f, 0.40f, 1.0f) : V4(0.35f, 1.0f, 0.55f, 1.0f), "%s", status_.c_str());
                ImGui::PopTextWrapPos();
                if (auth_offline_ || StatusLooksLikeAuthIssue(status_))
                    RenderAuthRecoveryPanel(card_w);
            }
            ImGui::EndChild();

            ImGui::SetCursorPos(ImVec2(50, size.y - 74));
            TextMuted("(c) 2026 Aegis Automation Suite");
        }

        bool StatusLooksLikeError(const std::string& value) const
        {
            const std::string lower = aegis::Lower(value);
            return lower.find("failed") != std::string::npos ||
                lower.find("could not") != std::string::npos ||
                lower.find("unable") != std::string::npos ||
                lower.find("enter your") != std::string::npos ||
                lower.find("needs") != std::string::npos ||
                lower.find("paused") != std::string::npos;
        }

        bool StatusLooksLikeAuthIssue(const std::string& value) const
        {
            const std::string lower = aegis::Lower(value);
            return lower.find("auth service") != std::string::npos ||
                lower.find("auth database") != std::string::npos ||
                lower.find("database is offline") != std::string::npos ||
                lower.find("503") != std::string::npos ||
                lower.find("login failed") != std::string::npos;
        }

        void ProbeAuthService()
        {
            const aegis::HttpResponse response = aegis::HttpGet(config_.auth_base_url);
            if (!response.error.empty())
            {
                auth_offline_ = true;
                status_ = "Auth service is offline at " + config_.auth_base_url + ". Start the website/auth server, then press Check Auth.";
                aegis::AppendDiagnosticLine("auth probe offline: " + response.error);
                return;
            }

            auth_offline_ = response.status_code < 200 || response.status_code >= 500;
            status_ = "Auth service responded HTTP " + std::to_string(response.status_code) + ". " +
                (auth_offline_ ? "The server or database still needs attention." : "Try signing in again.");
            aegis::AppendDiagnosticLine("auth probe status=" + std::to_string(response.status_code));
        }

        void RenderAuthRecoveryPanel(float card_w)
        {
            ImGui::Spacing();
            ImGui::BeginChild("auth_recovery", ImVec2(card_w - 32.0f, 92.0f), true, ImGuiWindowFlags_NoScrollbar);
            TextMuted("Auth recovery");
            ImGui::TextWrapped("Start or repair the website auth service at %s, then check it here before signing in again.", config_.auth_base_url.c_str());
            const float button_w = (ImGui::GetContentRegionAvail().x - 16.0f) / 3.0f;
            if (AegisButton("Check Auth", ImVec2(button_w, 30.0f), true))
                ProbeAuthService();
            ImGui::SameLine();
            if (AegisButton("Open Auth URL", ImVec2(button_w, 30.0f), false))
                aegis::OpenExternalUrl(config_.auth_base_url);
            ImGui::SameLine();
            if (AegisButton("Open Website", ImVec2(button_w, 30.0f), false))
                aegis::OpenExternalUrl(aegis::JoinUrl(config_.auth_base_url, config_.website_path));
            ImGui::EndChild();
        }

        void RenderLoading(ImVec2 size)
        {
            const double t = ImGui::GetTime();
            const float pulse = 0.5f + 0.5f * std::sin(static_cast<float>(t) * 3.0f);
            const float card_w = std::min(760.0f, std::max(520.0f, size.x - 80.0f));
            const float card_h = 430.0f;
            const ImVec2 card_pos(
                std::max(28.0f, (size.x - card_w) * 0.5f),
                std::max(82.0f, (size.y - card_h) * 0.5f));

            ImGui::SetCursorPos(card_pos);
            ImGui::BeginChild("loading_card", ImVec2(card_w, card_h), true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 card_min = ImGui::GetWindowPos();
            const ImVec2 card_max(card_min.x + card_w, card_min.y + card_h);
            draw->AddRect(card_min, card_max, Col(0.32f, 0.95f, 0.48f, 0.20f + pulse * 0.12f), 18.0f, 0, 1.4f);
            draw->AddCircleFilled(ImVec2(card_min.x + 112.0f, card_min.y + 128.0f), 82.0f, Col(0.19f, 0.85f, 0.42f, 0.045f + pulse * 0.035f));
            draw->AddCircle(ImVec2(card_min.x + 112.0f, card_min.y + 128.0f), 62.0f + pulse * 5.0f, Col(0.19f, 0.85f, 0.42f, 0.24f), 64, 1.2f);
            DrawLogo(draw, ImVec2(card_min.x + 112.0f, card_min.y + 128.0f), 70.0f, Col(0.32f, 1.0f, 0.55f, 1.0f));

            draw->AddText(g_font_bold, 13.0f, ImVec2(card_min.x + 214.0f, card_min.y + 72.0f), Col(0.35f, 1.0f, 0.55f, 1.0f), "LIVE SYNC");
            draw->AddText(g_font_title, 24.0f, ImVec2(card_min.x + 214.0f, card_min.y + 100.0f), Col(0.94f, 0.98f, 0.95f, 1.0f), sync_headline_.c_str());
            draw->AddText(g_font_regular, 14.0f, ImVec2(card_min.x + 214.0f, card_min.y + 136.0f), Col(0.62f, 0.67f, 0.64f, 1.0f), sync_detail_.c_str());

            const auto elapsed = std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::steady_clock::now() - sync_started_).count();
            const float progress = std::clamp(static_cast<float>(elapsed) / 9000.0f, 0.08f, 0.92f);
            const ImVec2 bar_min(card_min.x + 214.0f, card_min.y + 190.0f);
            const ImVec2 bar_max(card_min.x + card_w - 50.0f, card_min.y + 202.0f);
            draw->AddRectFilled(bar_min, bar_max, Col(0.06f, 0.10f, 0.08f, 1.0f), 999.0f);
            draw->AddRect(bar_min, bar_max, Col(0.77f, 0.87f, 0.81f, 0.16f), 999.0f);
            draw->AddRectFilled(bar_min, ImVec2(bar_min.x + (bar_max.x - bar_min.x) * progress, bar_max.y), Col(0.19f, 0.85f, 0.42f, 1.0f), 999.0f);

            DrawLoadingStep(draw, ImVec2(card_min.x + 214.0f, card_min.y + 232.0f), "1", "Validate session", elapsed >= 400);
            DrawLoadingStep(draw, ImVec2(card_min.x + 214.0f, card_min.y + 272.0f), "2", "Pull sportsbook, exchange, and model state", elapsed >= 1600);
            DrawLoadingStep(draw, ImVec2(card_min.x + 214.0f, card_min.y + 312.0f), "3", "Score predictions and market edges", elapsed >= 3200);
            DrawLoadingStep(draw, ImVec2(card_min.x + 214.0f, card_min.y + 352.0f), "4", "Render Aegis terminal", false);

            const std::string footer = status_.empty() ? "Preparing workspace." : status_;
            draw->AddText(g_font_regular, 14.0f, ImVec2(card_min.x + 34.0f, card_min.y + card_h - 54.0f), Col(0.62f, 0.67f, 0.64f, 1.0f), footer.c_str());
            ImGui::EndChild();
        }

        void DrawLoadingStep(ImDrawList* draw, ImVec2 pos, const char* step, const char* label, bool complete)
        {
            const ImU32 fill = complete ? Col(0.12f, 0.33f, 0.18f, 1.0f) : Col(0.06f, 0.10f, 0.08f, 1.0f);
            const ImU32 border = complete ? Col(0.35f, 1.0f, 0.55f, 0.55f) : Col(0.77f, 0.87f, 0.81f, 0.14f);
            draw->AddCircleFilled(ImVec2(pos.x + 13.0f, pos.y + 13.0f), 13.0f, fill);
            draw->AddCircle(ImVec2(pos.x + 13.0f, pos.y + 13.0f), 13.0f, border, 24, 1.2f);
            draw->AddText(g_font_bold, 13.0f, ImVec2(pos.x + 9.0f, pos.y + 5.0f), complete ? Col(0.35f, 1.0f, 0.55f, 1.0f) : Col(0.62f, 0.67f, 0.64f, 1.0f), step);
            draw->AddText(g_font_regular, 14.0f, ImVec2(pos.x + 36.0f, pos.y + 5.0f), complete ? Col(0.92f, 1.0f, 0.94f, 1.0f) : Col(0.62f, 0.67f, 0.64f, 1.0f), label);
        }

        void StyledInputText(const char* label, const char* id, char* buffer, size_t size, ImGuiInputTextFlags flags = 0)
        {
            if (std::strncmp(label, "##", 2) != 0)
                ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "%s", label);
            ImGui::PushStyleVar(ImGuiStyleVar_FrameRounding, 8.0f);
            ImGui::PushStyleVar(ImGuiStyleVar_FramePadding, ImVec2(14, 13));
            ImGui::PushStyleColor(ImGuiCol_FrameBg, V4(0.06f, 0.08f, 0.08f, 0.98f));
            ImGui::PushStyleColor(ImGuiCol_FrameBgHovered, V4(0.08f, 0.12f, 0.10f, 1.0f));
            ImGui::PushStyleColor(ImGuiCol_FrameBgActive, V4(0.08f, 0.16f, 0.10f, 1.0f));
            ImGui::PushStyleColor(ImGuiCol_Border, V4(0.78f, 0.88f, 0.82f, 0.16f));
            ImGui::InputText(id, buffer, size, flags);
            ImGui::PopStyleColor(4);
            ImGui::PopStyleVar(2);
        }

        void RenderShell(ImVec2 size)
        {
            const auto now = std::chrono::steady_clock::now();
            if (!refresh_in_flight_ && std::chrono::duration_cast<std::chrono::seconds>(now - last_refresh_).count() > config_.refresh_seconds)
                BeginSportsRefresh(false, "Refreshing live board", "Updating direct provider feeds, picks, and market links in the background.");

            const float top_h = 70.0f;
            const float side_w = 258.0f;
            const float status_h = 45.0f;
            RenderTopbar(size, top_h);

            ImGui::SetCursorPos(ImVec2(0, top_h));
            ImGui::BeginChild("sidebar", ImVec2(side_w, size.y - top_h - status_h), true);
            RenderSidebar();
            ImGui::EndChild();

            ImGui::SetCursorPos(ImVec2(side_w, top_h));
            ImGui::BeginChild("main", ImVec2(size.x - side_w, size.y - top_h - status_h), false);
            RenderMain(size.x - side_w);
            ImGui::EndChild();

            RenderStatusbar(size, status_h);
            if (show_drawer_)
                RenderPredictionDrawer(size);
        }

        void RenderTopbar(ImVec2 size, float top_h)
        {
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 origin = ImGui::GetWindowPos();
            draw->AddRectFilled(origin, ImVec2(origin.x + size.x, origin.y + top_h), Col(0.02f, 0.04f, 0.035f, 0.94f));
            draw->AddLine(ImVec2(origin.x, origin.y + top_h), ImVec2(origin.x + size.x, origin.y + top_h), Col(0.77f, 0.87f, 0.81f, 0.11f));
            DrawLogo(draw, ImVec2(origin.x + 36, origin.y + 35), 30.0f, Col(0.27f, 0.94f, 0.48f, 1.0f));
            ImGui::SetCursorPos(ImVec2(64, 21));
            ImGui::PushFont(g_font_bold);
            ImGui::TextUnformatted("Aegis Sports Betting");
            ImGui::PopFont();
            ImGui::SameLine(0, 5);
            TextGreen("AI");
            ImGui::SetCursorPos(ImVec2(64, 43));
            TextMuted(AppVersionLabel().c_str());

            const bool compact_topbar = size.x < 1280.0f;
            ImGui::SetCursorPos(ImVec2(292, 16));
            TopNavButton("Dashboard", View::Dashboard, IconKind::Dashboard, 118.0f);
            ImGui::SameLine();
            TopNavButton("Health", View::Health, IconKind::Lightning, 96.0f);
            ImGui::SameLine();
            TopNavButton("Picks", View::Picks, IconKind::Brain, 92.0f);
            ImGui::SameLine();
            TopNavButton("Markets", View::Arbitrage, IconKind::Scanner, 108.0f);
            if (!compact_topbar)
            {
                ImGui::SameLine();
                TopNavButton("Scenario", View::Scenario, IconKind::Chart, 112.0f);
                ImGui::SameLine();
                TopNavButton("Reports", View::Reports, IconKind::Report, 104.0f);
                ImGui::SameLine();
                TopNavButton("Settings", View::Settings, IconKind::Settings, 106.0f);
            }

            draw->PushClipRect(origin, ImVec2(origin.x + size.x, origin.y + top_h), true);
            const bool show_user = size.x >= 1320.0f;
            const float user_x = origin.x + size.x - 464.0f;
            const float user_y = origin.y + 14.0f;
            if (show_user)
            {
                draw->AddRectFilled(ImVec2(user_x - 12.0f, user_y + 2.0f), ImVec2(user_x + 305.0f, user_y + 48.0f), Col(0.025f, 0.07f, 0.045f, 0.88f), 11.0f);
                draw->AddRect(ImVec2(user_x - 12.0f, user_y + 2.0f), ImVec2(user_x + 305.0f, user_y + 48.0f), Col(0.77f, 0.87f, 0.81f, 0.13f), 11.0f);
                draw->AddRectFilled(ImVec2(user_x, user_y + 7.0f), ImVec2(user_x + 86.0f, user_y + 39.0f), Col(0.08f, 0.26f, 0.13f, 0.92f), 8.0f);
                DrawIcon(draw, ImVec2(user_x + 18.0f, user_y + 23.0f), 15.0f, IconKind::Shield, Col(0.35f, 1.0f, 0.55f, 1.0f), 1.4f);
                draw->AddText(g_font_bold, 14.0f, ImVec2(user_x + 32.0f, user_y + 15.0f), Col(0.35f, 1.0f, 0.55f, 1.0f), state_.tier.c_str());

                const float alert_x = user_x + 102.0f;
                draw->AddRectFilled(ImVec2(alert_x, user_y + 7.0f), ImVec2(alert_x + 34.0f, user_y + 41.0f), Col(0.06f, 0.10f, 0.08f, 0.94f), 8.0f);
                DrawIcon(draw, ImVec2(alert_x + 17.0f, user_y + 24.0f), 17.0f, IconKind::Bell, Col(0.78f, 0.88f, 0.82f, 0.88f), 1.4f);
                const std::string alert_count = std::to_string(state_.alerts.size());
                draw->AddCircleFilled(ImVec2(alert_x + 29.0f, user_y + 8.0f), 11.0f, Col(0.19f, 0.85f, 0.42f, 1.0f));
                draw->AddText(g_font_bold, 13.0f, ImVec2(alert_x + 25.0f, user_y + 1.0f), Col(0.02f, 0.08f, 0.04f, 1.0f), alert_count.c_str());
                ImGui::SetCursorScreenPos(ImVec2(alert_x, user_y + 7.0f));
                if (ImGui::InvisibleButton("alerts_topbar", ImVec2(38.0f, 38.0f)))
                    active_view_ = View::Alerts;

                const float avatar_x = user_x + 148.0f;
                DrawGeneratedLogoBadge(draw, ImVec2(avatar_x, user_y + 3.0f), ImVec2(42.0f, 42.0f), username_, IconKind::Users, true, true);
                draw->AddText(g_font_bold, 15.0f, ImVec2(avatar_x + 54.0f, user_y + 10.0f), Col(1, 1, 1, 1), username_.c_str());
                const std::string access_label = state_.tier + " access";
                draw->AddText(g_font_regular, 14.0f, ImVec2(avatar_x + 54.0f, user_y + 31.0f), Col(0.62f, 0.67f, 0.64f, 1.0f), access_label.c_str());
            }
            draw->PopClipRect();
        }

        void TopNavButton(const char* label, View view, IconKind icon, float width)
        {
            const bool active = active_view_ == view;
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            if (ImGui::InvisibleButton(label, ImVec2(width, 38.0f)))
                active_view_ = view;
            const bool hovered = ImGui::IsItemHovered();
            const ImU32 text_col = active ? Col(1, 1, 1, 1) : Col(0.76f, 0.82f, 0.78f, 1.0f);
            const ImVec2 text_size = ImGui::CalcTextSize(label);
            const float content_w = text_size.x + 23.0f;
            const float start_x = pos.x + (width - content_w) * 0.5f;
            if (active || hovered)
                draw->AddRectFilled(ImVec2(pos.x + 3.0f, pos.y + 1.0f), ImVec2(pos.x + width - 3.0f, pos.y + 37.0f), active ? Col(0.07f, 0.18f, 0.11f, 0.64f) : Col(0.07f, 0.11f, 0.09f, 0.56f), 9.0f);
            DrawIcon(draw, ImVec2(start_x + 8.0f, pos.y + 19.0f), 15.0f, icon, active ? Col(0.35f, 1.0f, 0.55f, 1.0f) : Col(0.62f, 0.67f, 0.64f, 1.0f), 1.4f);
            draw->AddText(g_font_bold, 16.0f, ImVec2(start_x + 22.0f, pos.y + 10.0f), text_col, label);
            if (active)
                draw->AddRectFilled(ImVec2(pos.x + 10.0f, pos.y + 52.0f), ImVec2(pos.x + width - 10.0f, pos.y + 55.0f), Col(0.19f, 0.85f, 0.42f, 1.0f), 999.0f);
        }

        void RenderSidebar()
        {
            SidebarSection("Readiness");
            SidebarButton("Setup", View::Setup, SetupReadyCount().c_str(), IconKind::Shield);
            SidebarButton("Health Center", View::Health, DataTrustLabel().c_str(), IconKind::Lightning);
            SidebarSection("Board");
            SidebarButton("Overview", View::Dashboard, "", IconKind::Dashboard);
            SidebarButton("Live Events", View::Live, CountStatus("live").c_str(), IconKind::Live, "live");
            SidebarButton("Upcoming", View::Live, CountStatus("scheduled").c_str(), IconKind::Calendar, "scheduled");
            if (!selected_game_id_.empty())
                SidebarButton("Game Detail", View::Details, "", IconKind::Trophy);
            SidebarButton("Alerts", View::Alerts, std::to_string(ReadNotificationItems(20).size()).c_str(), IconKind::Bell);
            SidebarSection("Research");
            SidebarButton("Market Scanner", View::Arbitrage, "", IconKind::Scanner);
            SidebarButton("Player Props", View::Props, config_.player_props_enabled ? "On" : "", IconKind::Prop);
            SidebarButton("AI Predictions", View::Picks, "", IconKind::Brain);
            SidebarButton("Scenario Lab", View::Scenario, "", IconKind::Chart);
            SidebarButton("Watchlist", View::Watchlist, std::to_string(watchlist_ids_.size()).c_str(), IconKind::Bell);
            SidebarSection("Operations");
            SidebarButton("Exposure", View::Exposure, FormatMoneyText(TodayPreviewExposure()).c_str(), IconKind::Wallet);
            SidebarButton("Reports", View::Reports, "", IconKind::Report);
            SidebarButton("Settings", View::Settings, "", IconKind::Settings);
        }

        void SidebarSection(const char* label)
        {
            ImGui::Spacing();
            ImGui::SetCursorPosX(ImGui::GetCursorPosX() + 10.0f);
            ImGui::PushFont(g_font_bold);
            ImGui::TextColored(V4(0.42f, 0.54f, 0.48f, 1.0f), "%s", label);
            ImGui::PopFont();
            ImGui::SetCursorPosY(ImGui::GetCursorPosY() - 3.0f);
        }

        void SidebarButton(const char* label, View view, const char* meta, IconKind icon, const char* filter = nullptr)
        {
            const bool has_filter = filter != nullptr && filter[0] != '\0';
            const bool active = active_view_ == view && (!has_filter || active_filter_ == filter);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const float width = ImGui::GetContentRegionAvail().x;
            const ImVec2 size(width, 47.0f);
            ImGui::PushID(label);
            if (ImGui::InvisibleButton("side_hit", size))
            {
                active_view_ = view;
                if (has_filter)
                    active_filter_ = filter;
            }
            const bool hovered = ImGui::IsItemHovered();
            const ImU32 bg = active ? Col(0.07f, 0.19f, 0.11f, 0.92f) : hovered ? Col(0.055f, 0.095f, 0.075f, 0.78f) : Col(0.04f, 0.07f, 0.06f, 0.46f);
            draw->AddRectFilled(pos, ImVec2(pos.x + width, pos.y + size.y), bg, 8.0f);
            draw->AddRect(pos, ImVec2(pos.x + width, pos.y + size.y), Col(0.77f, 0.87f, 0.81f, active ? 0.16f : 0.08f), 8.0f);
            if (active)
                draw->AddRectFilled(pos, ImVec2(pos.x + 3.0f, pos.y + size.y), Col(0.19f, 0.85f, 0.42f, 1.0f), 999.0f);
            DrawIconBadge(draw, ImVec2(pos.x + 14.0f, pos.y + 12.0f), ImVec2(28.0f, 28.0f), icon, active);
            draw->AddText(g_font_bold, 16.0f, ImVec2(pos.x + 54.0f, pos.y + 17.0f), active ? Col(1, 1, 1, 1) : Col(0.68f, 0.73f, 0.70f, 1.0f), label);
            if (meta != nullptr && meta[0] != '\0')
            {
                const ImVec2 meta_size = ImGui::CalcTextSize(meta);
                const float badge_w = std::max(32.0f, meta_size.x + 16.0f);
                const ImVec2 badge_min(pos.x + width - badge_w - 12.0f, pos.y + 14.0f);
                draw->AddRectFilled(badge_min, ImVec2(badge_min.x + badge_w, badge_min.y + 24.0f), Col(0.09f, 0.25f, 0.13f, 1.0f), 7.0f);
                draw->AddText(g_font_bold, 14.0f, ImVec2(badge_min.x + (badge_w - meta_size.x) * 0.5f, badge_min.y + 4.0f), Col(0.35f, 1.0f, 0.55f, 1.0f), meta);
            }
            ImGui::PopID();
            ImGui::Dummy(ImVec2(1, 8));
        }

        std::string CountStatus(const std::string& status) const
        {
            return std::to_string(CountStatusValue(status));
        }

        int CountStatusValue(const std::string& status) const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                if (aegis::Lower(game.status_key) == status)
                    ++count;
            }
            return count;
        }

        int CountMarketGames() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                if (game.spread_favorite != "--" || game.total_over != "--")
                    ++count;
            }
            return count;
        }

        int CountAvailableBookLines() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    if (link.available && link.kind == "Sportsbook")
                        ++count;
                }
            }
            return count;
        }

        int CountOddsMatchedGames() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                const std::string status = aegis::Lower(game.odds_match_status);
                if (status.find("matched") != std::string::npos)
                    ++count;
            }
            return count;
        }

        int CountOddsIssueGames() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                const std::string status = aegis::Lower(game.odds_match_status);
                if (status.find("no odds") != std::string::npos ||
                    status.find("no match") != std::string::npos ||
                    status.find("unsupported") != std::string::npos ||
                    status.find("fallback") != std::string::npos)
                {
                    ++count;
                }
            }
            return count;
        }

        int CountAvailableExchangeLinks() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    const std::string key = aegis::Lower(link.kind + " " + link.title + " " + link.provider_key);
                    if (key.find("kalshi") != std::string::npos || key.find("exchange") != std::string::npos)
                        ++count;
                }
            }
            return count;
        }

        int SecondsSinceLastRefresh() const
        {
            return static_cast<int>(std::max<long long>(0, std::chrono::duration_cast<std::chrono::seconds>(std::chrono::steady_clock::now() - last_refresh_).count()));
        }

        std::string AgeLabel(int seconds) const
        {
            if (seconds < 60)
                return std::to_string(seconds) + "s ago";
            const int minutes = seconds / 60;
            if (minutes < 60)
                return std::to_string(minutes) + "m ago";
            return std::to_string(minutes / 60) + "h ago";
        }

        std::string AppVersionLabel() const
        {
            return "v" + std::string(aegis::kAppVersion) + " / " + aegis::kAppCodename + " / " + aegis::kAppBuildDate;
        }

        bool IsBoardStale() const
        {
            return SecondsSinceLastRefresh() > std::max(30, config_.refresh_seconds * 2);
        }

        int DataTrustScore() const
        {
            int score = 28;
            if (!state_.games.empty())
                score += 18;
            if (CountStatusValue("live") > 0 || CountStatusValue("scheduled") > 0)
                score += 12;
            if (state_.source_badge.find("Fallback") == std::string::npos && state_.source_badge.find("Demo") == std::string::npos)
                score += 18;
            if (CountMarketGames() > 0)
                score += 10;
            if (CountAvailableBookLines() > 0)
                score += 8;
            if (!IsBoardStale())
                score += 6;
            if (!aegis::Trim(config_.odds_api_key).empty())
                score += 4;
            if (CountOddsIssueGames() > 0)
                score -= std::min(14, CountOddsIssueGames() * 2);
            return std::clamp(score, 0, 100);
        }

        std::string DataTrustLabel() const
        {
            if (refresh_in_flight_)
                return "Syncing";
            const int score = DataTrustScore();
            if (IsBoardStale())
                return "Stale";
            if (score >= 82)
                return "Strong";
            if (score >= 64)
                return "Usable";
            return "Limited";
        }

        bool GameHasMarketLine(const aegis::Game& game) const
        {
            if (game.spread_favorite != "--" || game.total_over != "--")
                return true;
            return std::any_of(game.bet_links.begin(), game.bet_links.end(), [](const aegis::BetLink& link) {
                return link.available && !link.price.empty() && link.price != "--";
            });
        }

        bool GamePassesAdvancedFilters(const aegis::Game& game) const
        {
            if (filter_favorites_only_ && !GameMatchesFavorites(game))
                return false;
            if (filter_watchlist_only_ && !IsWatched(game.id))
                return false;
            if (filter_market_lines_only_ && !GameHasMarketLine(game))
                return false;
            if (filter_actionable_only_)
            {
                if (game.status_key == "final" || !GameHasMarketLine(game))
                    return false;
                const aegis::Prediction* prediction = PredictionByGameId(game.id);
                if (prediction != nullptr && prediction->confidence_value < config_.min_ticket_confidence)
                    return false;
            }
            return true;
        }

        std::vector<const aegis::Game*> VisibleGames() const
        {
            std::vector<const aegis::Game*> games;
            for (const aegis::Game* game : aegis::FilterGames(state_, active_filter_, search_))
            {
                if (game != nullptr && GamePassesAdvancedFilters(*game))
                    games.push_back(game);
            }
            return games;
        }

        bool PredictionPassesAdvancedFilters(const aegis::Prediction& prediction) const
        {
            if (prediction.confidence_value < confidence_filter_)
                return false;
            const aegis::Game* game = GameById(prediction.game_id);
            if (filter_favorites_only_ && (game == nullptr || !GameMatchesFavorites(*game)))
                return false;
            if (filter_watchlist_only_ && !IsWatched(prediction.game_id))
                return false;
            if (filter_market_lines_only_ && (game == nullptr || !GameHasMarketLine(*game)))
                return false;
            if (filter_actionable_only_)
            {
                if (prediction.status_key == "final" || prediction.confidence_value < config_.min_ticket_confidence)
                    return false;
                if (game == nullptr || !GameHasMarketLine(*game))
                    return false;
            }
            return true;
        }

        std::vector<int> VisiblePredictionIndexes() const
        {
            std::vector<int> indexes;
            for (const int index : aegis::FilterPredictionIndexes(state_, active_filter_, search_))
            {
                if (index < 0 || index >= static_cast<int>(state_.predictions.size()))
                    continue;
                const aegis::Prediction& prediction = state_.predictions[static_cast<size_t>(index)];
                if (PredictionPassesAdvancedFilters(prediction))
                    indexes.push_back(index);
            }
            std::sort(indexes.begin(), indexes.end(), [this](int left_index, int right_index) {
                const aegis::Prediction& left = state_.predictions[static_cast<size_t>(left_index)];
                const aegis::Prediction& right = state_.predictions[static_cast<size_t>(right_index)];
                if (prediction_sort_mode_ == 1)
                    return NumberFromText(left.edge) > NumberFromText(right.edge);
                if (prediction_sort_mode_ == 2)
                    return NumberFromText(left.expected_value) > NumberFromText(right.expected_value);
                if (prediction_sort_mode_ == 3)
                    return left.market == right.market ? left.confidence_value > right.confidence_value : left.market < right.market;
                if (prediction_sort_mode_ == 4)
                {
                    const bool left_watched = IsWatched(left.game_id);
                    const bool right_watched = IsWatched(right.game_id);
                    if (left_watched != right_watched)
                        return left_watched;
                }
                return left.confidence_value > right.confidence_value;
            });
            return indexes;
        }

        std::vector<std::string> CsvTokens(const std::string& text) const
        {
            std::vector<std::string> tokens;
            std::stringstream stream(text);
            std::string token;
            while (std::getline(stream, token, ','))
            {
                token = aegis::Lower(aegis::Trim(token));
                if (!token.empty())
                    tokens.push_back(token);
            }
            return tokens;
        }

        bool ContainsAnyToken(const std::string& haystack, const std::vector<std::string>& tokens) const
        {
            if (tokens.empty())
                return false;
            const std::string lower = aegis::Lower(haystack);
            for (const std::string& token : tokens)
            {
                if (!token.empty() && lower.find(token) != std::string::npos)
                    return true;
            }
            return false;
        }

        bool GameMatchesFavorites(const aegis::Game& game) const
        {
            const std::vector<std::string> teams = CsvTokens(config_.favorite_teams);
            const std::vector<std::string> leagues = CsvTokens(config_.favorite_leagues);
            if (teams.empty() && leagues.empty())
                return false;
            const std::string team_haystack = game.matchup + " " + game.away.name + " " + game.away.abbr + " " + game.home.name + " " + game.home.abbr;
            const std::string league_haystack = game.league + " " + game.league_key + " " + game.sport_group;
            return ContainsAnyToken(team_haystack, teams) || ContainsAnyToken(league_haystack, leagues);
        }

        std::string DateLabelOffset(int days) const
        {
            const auto now = std::chrono::system_clock::now() + std::chrono::hours(24 * days);
            const std::time_t tt = std::chrono::system_clock::to_time_t(now);
            std::tm local{};
            localtime_s(&local, &tt);
            std::ostringstream stream;
            stream << std::put_time(&local, "%Y-%m-%d");
            return stream.str();
        }

        std::string IsoDatePrefix(const std::string& value) const
        {
            if (value.size() >= 10 &&
                std::isdigit(static_cast<unsigned char>(value[0])) != 0 &&
                std::isdigit(static_cast<unsigned char>(value[1])) != 0 &&
                std::isdigit(static_cast<unsigned char>(value[2])) != 0 &&
                std::isdigit(static_cast<unsigned char>(value[3])) != 0 &&
                value[4] == '-' &&
                std::isdigit(static_cast<unsigned char>(value[5])) != 0 &&
                std::isdigit(static_cast<unsigned char>(value[6])) != 0 &&
                value[7] == '-' &&
                std::isdigit(static_cast<unsigned char>(value[8])) != 0 &&
                std::isdigit(static_cast<unsigned char>(value[9])) != 0)
            {
                return value.substr(0, 10);
            }
            return {};
        }

        std::string ReportSportFilterValue() const
        {
            const char* values[] = {
                "all", "live", "scheduled", "league:nba", "league:wnba", "league:ncaab", "league:ncaaw", "league:nfl", "league:ncaaf", "league:ufl",
                "league:mlb", "league:college-baseball", "league:college-softball", "league:nhl", "league:ncaa-hockey", "group:soccer", "league:mls", "group:combat", "group:tennis",
                "group:golf", "group:racing", "group:cricket", "group:rugby", "group:lacrosse", "group:volleyball", "group:esports"
            };
            const int index = std::clamp(report_sport_filter_, 0, static_cast<int>(IM_ARRAYSIZE(values)) - 1);
            return values[index];
        }

        std::string ReportDateFilterLabel() const
        {
            const char* labels[] = { "All dates", "Today", "Next 7 days", "Upcoming only", "Finals only" };
            const int index = std::clamp(report_date_filter_, 0, static_cast<int>(IM_ARRAYSIZE(labels)) - 1);
            return labels[index];
        }

        std::string ReportProviderFilterLabel() const
        {
            const char* labels[] = { "All providers", "Sportsbook lines", "Kalshi/exchange", "Any priced line", "No priced line" };
            const int index = std::clamp(report_provider_filter_, 0, static_cast<int>(IM_ARRAYSIZE(labels)) - 1);
            return labels[index];
        }

        std::string ReportProviderFilterValue() const
        {
            const char* values[] = { "all", "sportsbook", "exchange", "priced", "unpriced" };
            const int index = std::clamp(report_provider_filter_, 0, static_cast<int>(IM_ARRAYSIZE(values)) - 1);
            return values[index];
        }

        std::string ReportMarketFilterLabel() const
        {
            const char* labels[] = { "All markets", "Spread", "Total", "Moneyline", "Props", "Unpriced only" };
            const int index = std::clamp(report_market_filter_, 0, static_cast<int>(IM_ARRAYSIZE(labels)) - 1);
            return labels[index];
        }

        std::string ReportMarketFilterValue() const
        {
            const char* values[] = { "all", "spread", "total", "moneyline", "props", "unpriced" };
            const int index = std::clamp(report_market_filter_, 0, static_cast<int>(IM_ARRAYSIZE(values)) - 1);
            return values[index];
        }

        bool ReportLeagueMatches(const std::string& haystack) const
        {
            const std::string query = aegis::Lower(aegis::Trim(report_league_filter_));
            if (query.empty())
                return true;
            if (query.find(',') != std::string::npos)
                return ContainsAnyToken(haystack, CsvTokens(query));
            const std::string lower = aegis::Lower(haystack);
            std::stringstream stream(query);
            std::string term;
            while (stream >> term)
            {
                if (lower.find(term) == std::string::npos)
                    return false;
            }
            return true;
        }

        bool ReportSportMatches(const aegis::Game& game) const
        {
            const std::string filter = ReportSportFilterValue();
            const std::string league_key = aegis::Lower(game.league_key);
            const std::string league = aegis::Lower(game.league);
            const std::string group = aegis::Lower(game.sport_group);
            if (filter == "all")
                return true;
            if (filter == "live")
                return aegis::Lower(game.status_key) == "live";
            if (filter == "scheduled")
                return aegis::Lower(game.status_key) == "scheduled";
            if (filter.rfind("league:", 0) == 0)
            {
                const std::string wanted = filter.substr(7);
                return league_key == wanted || league.find(wanted) != std::string::npos;
            }
            if (filter.rfind("group:", 0) == 0)
            {
                const std::string wanted = filter.substr(6);
                return group == wanted || group.find(wanted) != std::string::npos;
            }
            return true;
        }

        bool ReportDateMatches(const aegis::Game& game) const
        {
            const std::string status = aegis::Lower(game.status_key);
            if (report_date_filter_ == 0)
                return true;
            if (report_date_filter_ == 3)
                return status != "final";
            if (report_date_filter_ == 4)
                return status == "final";
            const std::string prefix = IsoDatePrefix(game.start_time);
            if (report_date_filter_ == 1)
                return prefix == TodayDateLabel() || status == "live";
            if (report_date_filter_ == 2)
            {
                if (prefix.empty())
                    return status == "live" || status == "scheduled";
                for (int day = 0; day < 7; ++day)
                {
                    if (prefix == DateLabelOffset(day))
                        return true;
                }
                return false;
            }
            return true;
        }

        bool LinkMatchesReportProvider(const aegis::BetLink& link) const
        {
            const std::string filter = ReportProviderFilterValue();
            if (filter == "all" || filter == "priced" || filter == "unpriced")
                return true;
            const std::string key = aegis::Lower(link.provider_key + " " + link.title + " " + link.kind);
            if (filter == "sportsbook")
                return link.kind == "Sportsbook" || key.find("sportsbook") != std::string::npos;
            if (filter == "exchange")
                return link.kind == "Exchange" || key.find("exchange") != std::string::npos || key.find("kalshi") != std::string::npos;
            return true;
        }

        bool LinkMatchesReportMarket(const aegis::BetLink& link) const
        {
            const std::string filter = ReportMarketFilterValue();
            if (filter == "all" || filter == "unpriced")
                return true;
            const std::string key = aegis::Lower(link.market + " " + link.line + " " + link.note + " " + link.kind);
            if (filter == "spread")
                return key.find("spread") != std::string::npos;
            if (filter == "total")
                return key.find("total") != std::string::npos || key.find("over") != std::string::npos || key.find("under") != std::string::npos;
            if (filter == "moneyline")
                return key.find("moneyline") != std::string::npos || key.find("h2h") != std::string::npos;
            if (filter == "props")
                return key.find("prop") != std::string::npos || key.find("player") != std::string::npos;
            return true;
        }

        bool GameHasReportProvider(const aegis::Game& game) const
        {
            const std::string filter = ReportProviderFilterValue();
            if (filter == "all")
                return true;
            const bool priced = GameHasMarketLine(game);
            if (filter == "priced")
                return priced;
            if (filter == "unpriced")
                return !priced;
            for (const aegis::BetLink& link : game.bet_links)
            {
                if (link.available && LinkMatchesReportProvider(link))
                    return true;
            }
            return false;
        }

        bool GameHasReportMarket(const aegis::Game& game) const
        {
            const std::string filter = ReportMarketFilterValue();
            if (filter == "all")
                return true;
            if (filter == "unpriced")
                return !GameHasMarketLine(game);
            if (filter == "spread" && game.spread_favorite != "--")
                return true;
            if (filter == "total" && game.total_over != "--")
                return true;
            for (const aegis::BetLink& link : game.bet_links)
            {
                if (LinkMatchesReportMarket(link))
                    return true;
            }
            return false;
        }

        bool ReportGamePassesFilters(const aegis::Game& game) const
        {
            if (!ReportSportMatches(game))
                return false;
            if (!ReportDateMatches(game))
                return false;
            if (report_watchlist_only_ && !IsWatched(game.id))
                return false;
            const std::string league_haystack = game.league + " " + game.league_key + " " + game.sport_group + " " + game.matchup + " " + game.away.name + " " + game.home.name;
            if (!ReportLeagueMatches(league_haystack))
                return false;
            if (!GameHasReportProvider(game))
                return false;
            if (!GameHasReportMarket(game))
                return false;
            return true;
        }

        bool ReportPredictionPassesFilters(const aegis::Prediction& prediction) const
        {
            const aegis::Game* game = GameById(prediction.game_id);
            if (game != nullptr)
                return ReportGamePassesFilters(*game);

            if (report_watchlist_only_ && !IsWatched(prediction.game_id))
                return false;
            const std::string filter = ReportSportFilterValue();
            if (filter.rfind("league:", 0) == 0)
            {
                const std::string wanted = filter.substr(7);
                const std::string league = aegis::Lower(prediction.league);
                if (league.find(wanted) == std::string::npos)
                    return false;
            }
            if (filter.rfind("group:", 0) == 0)
            {
                const std::string wanted = filter.substr(6);
                const std::string group = aegis::Lower(prediction.sport_group);
                if (group.find(wanted) == std::string::npos)
                    return false;
            }
            if (!ReportLeagueMatches(prediction.league + " " + prediction.sport_group + " " + prediction.matchup))
                return false;
            const std::string provider = ReportProviderFilterValue();
            if (provider != "all")
            {
                const bool priced = HasAmericanOddsText(prediction.odds) || !prediction.market_links.empty();
                if (provider == "priced" && !priced)
                    return false;
                if (provider == "unpriced" && priced)
                    return false;
                if (provider == "sportsbook" || provider == "exchange")
                {
                    bool matched = false;
                    for (const aegis::BetLink& link : prediction.market_links)
                    {
                        if (LinkMatchesReportProvider(link))
                            matched = true;
                    }
                    if (!matched)
                        return false;
                }
            }
            const std::string market = ReportMarketFilterValue();
            if (market != "all")
            {
                const std::string key = aegis::Lower(prediction.market + " " + prediction.book_line + " " + prediction.pick);
                const bool unpriced = !HasAmericanOddsText(prediction.odds) && prediction.market_links.empty();
                if (market == "unpriced")
                    return unpriced;
                if (market == "spread" && key.find("spread") == std::string::npos)
                    return false;
                if (market == "total" && key.find("total") == std::string::npos && key.find("over") == std::string::npos && key.find("under") == std::string::npos)
                    return false;
                if (market == "moneyline" && key.find("moneyline") == std::string::npos && key.find("h2h") == std::string::npos)
                    return false;
                if (market == "props" && key.find("prop") == std::string::npos && key.find("player") == std::string::npos)
                    return false;
            }
            return true;
        }

        std::vector<const aegis::Game*> ReportFilteredGames() const
        {
            std::vector<const aegis::Game*> games;
            for (const aegis::Game& game : state_.games)
            {
                if (ReportGamePassesFilters(game))
                    games.push_back(&game);
            }
            return games;
        }

        std::vector<const aegis::Prediction*> ReportFilteredPredictions() const
        {
            std::vector<const aegis::Prediction*> predictions;
            for (const aegis::Prediction& prediction : state_.predictions)
            {
                if (ReportPredictionPassesFilters(prediction))
                    predictions.push_back(&prediction);
            }
            std::sort(predictions.begin(), predictions.end(), [](const aegis::Prediction* left, const aegis::Prediction* right) {
                return left->confidence_value > right->confidence_value;
            });
            return predictions;
        }

        bool ReportSnapshotPairPassesFilters(const std::pair<MarketSnapshotRow, MarketSnapshotRow>& pair) const
        {
            const std::string haystack = aegis::Lower(pair.second.matchup + " " + pair.second.book + " " + pair.second.line);
            const aegis::Game* matched_game = nullptr;
            for (const aegis::Game& game : state_.games)
            {
                if (!game.matchup.empty() && haystack.find(aegis::Lower(game.matchup)) != std::string::npos)
                {
                    matched_game = &game;
                    break;
                }
            }
            if (matched_game != nullptr)
                return ReportGamePassesFilters(*matched_game);

            const bool needs_game_context =
                ReportSportFilterValue() != "all" ||
                report_date_filter_ != 0 ||
                report_watchlist_only_ ||
                !aegis::Trim(report_league_filter_).empty();
            if (needs_game_context)
                return false;
            const std::string provider = ReportProviderFilterValue();
            if (provider == "exchange" && haystack.find("kalshi") == std::string::npos && haystack.find("exchange") == std::string::npos)
                return false;
            if (provider == "unpriced")
                return false;
            const std::string market = ReportMarketFilterValue();
            if (market == "spread" && haystack.find("spread") == std::string::npos)
                return false;
            if (market == "total" && haystack.find("total") == std::string::npos && haystack.find("over") == std::string::npos && haystack.find("under") == std::string::npos)
                return false;
            if (market == "moneyline" && haystack.find("moneyline") == std::string::npos && haystack.find("h2h") == std::string::npos)
                return false;
            if (market == "props" && haystack.find("prop") == std::string::npos && haystack.find("player") == std::string::npos)
                return false;
            if (market == "unpriced")
                return false;
            return true;
        }

        std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> ReportFilteredMarketPairs(int max_rows = 1000) const
        {
            std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> pairs;
            for (const auto& pair : MarketSnapshotPairs(max_rows))
            {
                if (ReportSnapshotPairPassesFilters(pair))
                    pairs.push_back(pair);
            }
            return pairs;
        }

        std::string ReportFilterSummary() const
        {
            const std::string league = aegis::Trim(report_league_filter_);
            std::vector<std::string> parts;
            parts.push_back(ReportSportFilterValue() == "all" ? "All sports" : ReportSportFilterValue());
            parts.push_back(ReportDateFilterLabel());
            parts.push_back(ReportProviderFilterLabel());
            parts.push_back(ReportMarketFilterLabel());
            if (!league.empty())
                parts.push_back("league/team " + league);
            if (report_watchlist_only_)
                parts.push_back("watchlist only");
            std::ostringstream stream;
            for (size_t i = 0; i < parts.size(); ++i)
            {
                if (i > 0)
                    stream << " / ";
                stream << parts[i];
            }
            return stream.str();
        }

        int CountFavoriteGames() const
        {
            int count = 0;
            for (const aegis::Game& game : state_.games)
            {
                if (GameMatchesFavorites(game))
                    ++count;
            }
            return count;
        }

        double NumberFromText(const std::string& text) const
        {
            std::string cleaned;
            for (const char c : text)
            {
                if ((c >= '0' && c <= '9') || c == '.' || c == '-' || c == '+')
                    cleaned.push_back(c);
            }
            if (cleaned.empty() || cleaned == "+" || cleaned == "-")
                return 0.0;
            return std::atof(cleaned.c_str());
        }

        void DrawHeroMetric(ImDrawList* draw, ImVec2 pos, ImVec2 size, const char* label, const std::string& value, const char* detail, IconKind icon, ImU32 accent)
        {
            draw->AddRectFilled(pos, ImVec2(pos.x + size.x, pos.y + size.y), Col(0.035f, 0.055f, 0.052f, 0.92f), 10.0f);
            draw->AddRect(pos, ImVec2(pos.x + size.x, pos.y + size.y), Col(0.85f, 0.95f, 0.90f, 0.13f), 10.0f);
            draw->AddRectFilled(pos, ImVec2(pos.x + 3.0f, pos.y + size.y), accent, 999.0f);
            DrawIconBadge(draw, ImVec2(pos.x + 14.0f, pos.y + 16.0f), ImVec2(32.0f, 32.0f), icon, true);
            draw->AddText(g_font_regular, 13.0f, ImVec2(pos.x + 58.0f, pos.y + 13.0f), Col(0.62f, 0.67f, 0.64f, 1.0f), label);
            draw->AddText(g_font_title, 22.0f, ImVec2(pos.x + 58.0f, pos.y + 34.0f), Col(0.94f, 0.98f, 0.95f, 1.0f), value.c_str());
            draw->AddText(g_font_regular, 12.0f, ImVec2(pos.x + 14.0f, pos.y + size.y - 24.0f), Col(0.52f, 0.60f, 0.57f, 1.0f), detail);
        }

        void RenderDashboardHero(float width)
        {
            ImGui::BeginChild("dashboard_command", ImVec2(0, 146.0f), true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetWindowPos();
            const ImVec2 size = ImGui::GetWindowSize();
            draw->AddRectFilledMultiColor(
                pos,
                ImVec2(pos.x + size.x, pos.y + size.y),
                Col(0.04f, 0.11f, 0.08f, 0.62f),
                Col(0.025f, 0.055f, 0.060f, 0.72f),
                Col(0.020f, 0.034f, 0.036f, 0.72f),
                Col(0.035f, 0.055f, 0.045f, 0.62f));
            draw->AddLine(ImVec2(pos.x + 20.0f, pos.y + size.y - 1.0f), ImVec2(pos.x + size.x - 20.0f, pos.y + size.y - 1.0f), Col(0.32f, 0.95f, 0.48f, 0.20f), 1.0f);

            ImGui::SetCursorScreenPos(ImVec2(pos.x + 24.0f, pos.y + 20.0f));
            TextGreen("DIRECT SOURCE MODE");
            ImGui::PushFont(g_font_title);
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 24.0f, pos.y + 44.0f));
            ImGui::TextUnformatted("Native Market Command");
            ImGui::PopFont();
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 24.0f, pos.y + 78.0f));
            const float text_max = width < 980.0f ? pos.x + width - 28.0f : pos.x + width * 0.40f;
            ImGui::PushTextWrapPos(text_max);
            ImGui::PushStyleColor(ImGuiCol_Text, V4(0.62f, 0.67f, 0.64f, 1.0f));
            ImGui::TextWrapped("%s", state_.source_label.c_str());
            ImGui::PopStyleColor();
            ImGui::PopTextWrapPos();

            const bool compact = width < 980.0f;
            if (compact)
            {
                ImGui::Dummy(ImVec2(1, 124.0f));
                ImGui::EndChild();
                return;
            }

            const float tile_y = pos.y + 24.0f;
            const float tile_x = pos.x + width * 0.43f;
            const float tile_w = std::max(116.0f, (pos.x + width - tile_x - 38.0f) / 4.0f);
            const ImVec2 tile_size(tile_w, 98.0f);
            DrawHeroMetric(draw, ImVec2(tile_x, tile_y), tile_size, "Live", CountStatus("live"), "in progress", IconKind::Live, Col(0.30f, 0.95f, 0.48f, 0.95f));
            DrawHeroMetric(draw, ImVec2(tile_x + (tile_w + 10.0f), tile_y), tile_size, "Upcoming", CountStatus("scheduled"), "in window", IconKind::Calendar, Col(0.55f, 0.82f, 1.0f, 0.95f));
            DrawHeroMetric(draw, ImVec2(tile_x + (tile_w + 10.0f) * 2.0f, tile_y), tile_size, "Markets", std::to_string(CountMarketGames()), "with lines", IconKind::Chart, Col(0.94f, 0.66f, 0.23f, 0.95f));
            DrawHeroMetric(draw, ImVec2(tile_x + (tile_w + 10.0f) * 3.0f, tile_y), tile_size, "Trust", std::to_string(DataTrustScore()) + "%", DataTrustLabel().c_str(), IconKind::Shield, Col(0.53f, 0.42f, 1.0f, 0.95f));
            ImGui::Dummy(ImVec2(1, 124.0f));
            ImGui::EndChild();
        }

        void RenderTrustBar(float width)
        {
            ImGui::BeginChild("trust_bar", ImVec2(0, 66.0f), true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const float score_w = std::min(220.0f, width * 0.22f);
            draw->AddRectFilled(ImVec2(pos.x, pos.y + 7.0f), ImVec2(pos.x + score_w, pos.y + 35.0f), Col(0.06f, 0.10f, 0.08f, 1.0f), 8.0f);
            draw->AddRectFilled(ImVec2(pos.x + 4.0f, pos.y + 11.0f), ImVec2(pos.x + 4.0f + (score_w - 8.0f) * (DataTrustScore() / 100.0f), pos.y + 31.0f), Col(0.19f, 0.85f, 0.42f, 0.80f), 6.0f);
            draw->AddRect(ImVec2(pos.x, pos.y + 7.0f), ImVec2(pos.x + score_w, pos.y + 35.0f), Col(0.77f, 0.87f, 0.81f, 0.14f), 8.0f);
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 12.0f, pos.y + 12.0f));
            ImGui::TextColored(V4(0.02f, 0.08f, 0.04f, 1.0f), "Data trust %d%%", DataTrustScore());
            ImGui::SameLine(score_w + 18.0f);
            TextGreen(DataTrustLabel().c_str());
            ImGui::SameLine();
            ImGui::Text("Last refresh %s", AgeLabel(SecondsSinceLastRefresh()).c_str());
            ImGui::SameLine();
            TextMuted(("Cadence " + std::to_string(config_.refresh_seconds) + "s").c_str());
            ImGui::SameLine();
            TextMuted((std::to_string(CountAvailableBookLines()) + " book lines / " + std::to_string(CountAvailableExchangeLinks()) + " exchange links").c_str());
            if (IsBoardStale())
            {
                ImGui::SameLine();
                ImGui::TextColored(V4(1.0f, 0.45f, 0.24f, 1.0f), "Refresh recommended");
            }
            ImGui::EndChild();
        }

        void RenderCommandBrief(float width)
        {
            ImGui::BeginChild("command_brief", ImVec2(0, 142.0f), true, ImGuiWindowFlags_NoScrollbar);
            CardHeader("Command Brief", "Focus, alerts, and next action", active_view_);
            const float third = std::max(220.0f, (width - 52.0f) / 3.0f);
            const std::vector<aegis::InfoItem> rows = CommandBriefRows();
            for (int i = 0; i < 3 && i < static_cast<int>(rows.size()); ++i)
            {
                if (i > 0)
                    ImGui::SameLine();
                RenderInfoChip(rows[static_cast<size_t>(i)], ImVec2(third, 96.0f));
            }
            ImGui::EndChild();
        }

        void RenderMain(float width)
        {
            if (active_view_ != View::Setup && active_view_ != View::Health && active_view_ != View::Settings && active_view_ != View::Details && active_view_ != View::Alerts && active_view_ != View::Props && active_view_ != View::Scenario && active_view_ != View::Exposure && active_view_ != View::Reports)
                RenderFilters(width);
            switch (active_view_)
            {
            case View::Setup: RenderSetup(width); break;
            case View::Health: RenderHealth(width); break;
            case View::Dashboard: RenderDashboard(width); break;
            case View::Live: RenderLive(width); break;
            case View::Picks: RenderPicks(width, false); break;
            case View::Details: RenderGameDetail(width); break;
            case View::Alerts: RenderAlerts(width); break;
            case View::Analytics: RenderAnalytics(width); break;
            case View::Arbitrage: RenderArbitrage(width); break;
            case View::Props: RenderProps(width); break;
            case View::Scenario: RenderScenario(width); break;
            case View::Watchlist: RenderWatchlist(width); break;
            case View::Exposure: RenderExposure(width); break;
            case View::Reports: RenderReports(width); break;
            case View::Settings: RenderSettings(width); break;
            }
        }

        void RenderFilters(float width)
        {
            ImGui::Dummy(ImVec2(1, 10));
            const char* combo_labels[] = {
                "All Sports", "Live Now", "Upcoming", "NBA", "WNBA", "NCAAB", "NCAAW", "NFL", "College Football", "UFL",
                "MLB", "NCAA Baseball", "NCAA Softball", "NHL", "NCAA Hockey", "Soccer", "MLS", "Combat Sports", "Tennis",
                "Golf", "Racing", "Cricket", "Rugby", "Lacrosse", "Volleyball", "Esports"
            };
            const char* combo_values[] = {
                "all", "live", "scheduled", "league:nba", "league:wnba", "league:ncaab", "league:ncaaw", "league:nfl", "league:ncaaf", "league:ufl",
                "league:mlb", "league:college-baseball", "league:college-softball", "league:nhl", "league:ncaa-hockey", "group:soccer", "league:mls", "group:combat", "group:tennis",
                "group:golf", "group:racing", "group:cricket", "group:rugby", "group:lacrosse", "group:volleyball", "group:esports"
            };
            int current = 0;
            for (int i = 0; i < IM_ARRAYSIZE(combo_values); ++i)
            {
                if (active_filter_ == combo_values[i])
                    current = i;
            }
            ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "Sport");
            ImGui::SetNextItemWidth(std::min(260.0f, width * 0.28f));
            if (ImGui::Combo("##sport_combo", &current, combo_labels, IM_ARRAYSIZE(combo_labels)))
                active_filter_ = combo_values[current];
            ImGui::SameLine();
            ImGui::BeginGroup();
            ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "Search");
            ImGui::SetNextItemWidth(std::max(260.0f, width - 315.0f));
            StyledInputText("##hidden", "##sports_search", search_, sizeof(search_));
            ImGui::EndGroup();

            ImGui::Spacing();
            const char* tab_labels[] = { "All Sports", "NBA", "NFL", "MLB", "NHL", "Soccer", "UFC", "Tennis", "Esports", "More" };
            const char* tab_values[] = { "all", "league:nba", "league:nfl", "league:mlb", "league:nhl", "group:soccer", "league:ufc", "group:tennis", "group:esports", "" };
            const IconKind tab_icons[] = { IconKind::Ball, IconKind::Basketball, IconKind::Football, IconKind::Baseball, IconKind::Hockey, IconKind::Soccer, IconKind::Fight, IconKind::Tennis, IconKind::Esports, IconKind::More };
            for (int i = 0; i < IM_ARRAYSIZE(tab_labels); ++i)
            {
                if (i > 0)
                    ImGui::SameLine();
                if (AegisIconButton(tab_labels[i], tab_icons[i], ImVec2(i == 0 ? 128.0f : 104.0f, 42.0f), i != 9 && active_filter_ == tab_values[i]))
                {
                    if (i == 9)
                        ImGui::OpenPopup("more_sports_popup");
                    else
                        active_filter_ = tab_values[i];
                }
            }
            if (ImGui::BeginPopup("more_sports_popup"))
            {
                const char* more_labels[] = { "WNBA", "College Hoops", "College Football", "Golf", "Racing", "Cricket", "Rugby", "Lacrosse", "Volleyball" };
                const char* more_values[] = { "league:wnba", "league:ncaab", "league:ncaaf", "group:golf", "group:racing", "group:cricket", "group:rugby", "group:lacrosse", "group:volleyball" };
                ImGui::TextColored(V4(0.19f, 0.85f, 0.42f, 1.0f), "More markets");
                ImGui::Separator();
                for (int i = 0; i < IM_ARRAYSIZE(more_labels); ++i)
                {
                    if (ImGui::Selectable(more_labels[i], active_filter_ == more_values[i]))
                    {
                        active_filter_ = more_values[i];
                        active_view_ = View::Live;
                    }
                }
                ImGui::EndPopup();
            }

            ImGui::Spacing();
            ImGui::PushItemWidth(190.0f);
            ImGui::SliderInt("Min confidence", &confidence_filter_, 50, 85, "%d%%");
            ImGui::PopItemWidth();
            ImGui::SameLine();
            ImGui::Checkbox("Watched", &filter_watchlist_only_);
            ImGui::SameLine();
            ImGui::Checkbox("Favorites", &filter_favorites_only_);
            ImGui::SameLine();
            ImGui::Checkbox("With lines", &filter_market_lines_only_);
            ImGui::SameLine();
            ImGui::Checkbox("Actionable", &filter_actionable_only_);
            ImGui::SameLine();
            if (PlainLinkButton("Clear filters"))
            {
                confidence_filter_ = 50;
                filter_watchlist_only_ = false;
                filter_favorites_only_ = false;
                filter_market_lines_only_ = false;
                filter_actionable_only_ = false;
                search_[0] = '\0';
                active_filter_ = "all";
            }
            ImGui::Spacing();
        }

        void RenderDashboard(float width)
        {
            RenderDashboardHero(width);
            ImGui::Spacing();
            RenderTrustBar(width);
            ImGui::Spacing();
            RenderCommandBrief(width);
            ImGui::Spacing();

            const std::vector<const aegis::Game*> filtered = VisibleGames();
            std::vector<const aegis::Game*> live;
            std::vector<const aegis::Game*> featured;
            for (const aegis::Game* game : filtered)
            {
                if (aegis::Lower(game->status_key) == "live")
                    live.push_back(game);
            }
            featured = live;
            if (featured.empty())
            {
                for (const aegis::Game* game : filtered)
                {
                    if (aegis::Lower(game->status_key) != "final")
                        featured.push_back(game);
                }
            }
            if (featured.empty())
                featured = filtered;

            ImGui::BeginChild("live_card", ImVec2(0, 272), true);
            const std::string event_title = live.empty() ? "Next Best Events" : "Top Live Events";
            CardHeader(event_title.c_str(), state_.source_badge.c_str(), View::Live);
            const int count = std::min(4, static_cast<int>(featured.size()));
            const float card_w = count > 0 ? std::max(180.0f, (width - 54.0f) / static_cast<float>(count)) : width - 36.0f;
            for (int i = 0; i < count; ++i)
            {
                if (i > 0)
                    ImGui::SameLine();
                RenderEventCard(*featured[static_cast<size_t>(i)], ImVec2(card_w, 198));
            }
            if (count == 0)
                EmptyState("No events match this filter", "Clear the search or choose All Sports to rebuild the board.");
            ImGui::EndChild();

            ImGui::Spacing();
            ImGui::BeginChild("picks_card", ImVec2(0, 286), true);
            CardHeader("AI Top Picks", "Confidence, fair odds, edge, and EV", View::Picks);
            RenderPredictionTable(5, true);
            ImGui::EndChild();

            ImGui::Spacing();
            const float third = (width - 48.0f) / 3.0f;
            ImGui::BeginChild("insight_card", ImVec2(third, 278), true);
            CardHeader("AI Insight", "", View::Dashboard);
            ImGui::PushTextWrapPos(ImGui::GetCursorPosX() + third - 28.0f);
            ImGui::TextUnformatted(state_.insight_copy.c_str());
            ImGui::PopTextWrapPos();
            DrawSparkChart(PrimaryHistory(), ImVec2(third - 28.0f, 150.0f), Col(0.25f, 0.88f, 0.48f), "Win Confidence", LastPointLabel(PrimaryHistory()).c_str());
            ImGui::EndChild();
            ImGui::SameLine();
            ImGui::BeginChild("market_card", ImVec2(third, 278), true);
            CardHeader("Market Movement", state_.selected_market.c_str(), View::Analytics);
            ImGui::TextUnformatted(state_.primary_market.c_str());
            DrawSparkChart(state_.market_history, ImVec2(third - 28.0f, 165.0f), Col(0.55f, 0.88f, 1.0f), "Line Movement", state_.book_current.c_str());
            ImGui::EndChild();
            ImGui::SameLine();
            ImGui::BeginChild("alerts_card", ImVec2(third, 278), true);
            CardHeader("Alerts", "Signal feed", View::Analytics);
            RenderInfoList(state_.alerts, 3);
            ImGui::EndChild();
        }

        std::vector<float> PrimaryHistory() const
        {
            if (!state_.games.empty() && !state_.games[0].history.empty())
                return state_.games[0].history;
            return { 50, 54, 57, 62, 66, 71, 76, 78 };
        }

        std::string LastPointLabel(const std::vector<float>& points) const
        {
            if (points.empty())
                return "50%";
            return std::to_string(static_cast<int>(std::round(points.back()))) + "%";
        }

        void RenderLive(float)
        {
            ImGui::BeginChild("live_expanded", ImVec2(0, 0), true);
            const std::string count_label = std::to_string(VisibleGames().size()) + " visible / " + std::to_string(state_.games.size()) + " loaded";
            CardHeader("Live, Upcoming & Final Board", count_label.c_str(), View::Live);
            RenderEventSection("Live Now", "Games currently in progress", "live");
            RenderEventSection("Upcoming", "Scheduled games and pregame markets", "scheduled");
            RenderEventSection("Final", "Completed games kept for review", "final");
            if (VisibleGames().empty())
                EmptyState("No games match the active filters", "Clear filters or lower the confidence threshold to widen the board.");
            ImGui::EndChild();
        }

        void RenderEventSection(const char* title, const char* detail, const std::string& status)
        {
            std::vector<const aegis::Game*> all = VisibleGames();
            std::vector<const aegis::Game*> section;
            for (const aegis::Game* game : all)
            {
                if (aegis::Lower(game->status_key) == status)
                    section.push_back(game);
            }
            if (section.empty())
                return;
            ImGui::SeparatorText(title);
            TextMuted(detail);
            const float available = ImGui::GetContentRegionAvail().x;
            const float card_w = std::max(235.0f, (available - 24.0f) / 3.0f);
            for (size_t i = 0; i < section.size(); ++i)
            {
                if (i % 3 != 0)
                    ImGui::SameLine();
                RenderEventCard(*section[i], ImVec2(card_w, 204));
            }
            ImGui::Spacing();
        }

        void RenderPicks(float, bool compact)
        {
            ImGui::BeginChild(compact ? "picks_compact" : "picks_expanded", ImVec2(0, 0), true);
            CardHeader("Aegis AI Picks", "Confidence, fair odds, edge, and EV", View::Picks);
            if (!compact)
            {
                const char* sort_labels[] = { "Confidence", "Edge", "EV", "Market", "Watched First" };
                ImGui::SetNextItemWidth(190.0f);
                ImGui::Combo("Sort", &prediction_sort_mode_, sort_labels, IM_ARRAYSIZE(sort_labels));
                ImGui::SameLine();
                TextMuted((std::to_string(VisiblePredictionIndexes().size()) + " visible picks").c_str());
                ImGui::Spacing();
            }
            RenderPredictionTable(compact ? 5 : 16, false);
            ImGui::EndChild();
        }

        void RenderSetup(float)
        {
            ImGui::BeginChild("setup_workspace", ImVec2(0, 0), true);
            CardHeader("Setup Wizard", "Production readiness and safety checklist", active_view_);
            if (AegisButton("Save Settings", ImVec2(136.0f, 38.0f), true))
                SaveSourceSettings();
            ImGui::SameLine();
            if (AegisButton("Validate Odds", ImVec2(136.0f, 38.0f), false))
                ValidateOddsKey();
            ImGui::SameLine();
            if (AegisButton("Validate Feeds", ImVec2(146.0f, 38.0f), false))
                ValidateDataAdapters();
            ImGui::SameLine();
            if (AegisButton("Refresh Now", ImVec2(136.0f, 38.0f), false))
                BeginSportsRefresh(false, "Setup refresh", "Checking direct sources and app readiness.");
            ImGui::Spacing();
            RenderInfoGridCard("Setup Status", SetupStatusRows(), 0, 190.0f);
            RenderInfoGridCard("Startup Integrity", startup_integrity_, 0, 230.0f);
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Setup Checklist", SetupChecklistRows(), half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Responsible Use", ResponsibleUseRows(), half, 300.0f);
            RenderInfoGridCard("Data Adapter Setup", DataAdapterRows(), half, 340.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Release Readiness", ReleaseReadinessRows(), half, 340.0f);
            RenderDiagnosticsPanel();
            ImGui::EndChild();
        }

        void RenderHealth(float)
        {
            ImGui::BeginChild("health_center", ImVec2(0, 0), true);
            const std::string health_subtitle = "Source telemetry, readiness, and local refresh history / " + AppVersionLabel();
            CardHeader("Provider Health Center", health_subtitle.c_str(), View::Health);
            if (AegisButton("Refresh Now", ImVec2(136.0f, 38.0f), true))
                BeginSportsRefresh(false, "Health refresh", "Rechecking scoreboard, sportsbook, exchange, and configured adapter sources.");
            ImGui::SameLine();
            if (AegisButton("Validate Feeds", ImVec2(146.0f, 38.0f), false))
                ValidateDataAdapters();
            ImGui::SameLine();
            if (AegisButton("Export Health", ImVec2(146.0f, 38.0f), false))
                ExportProviderHealthReport();
            ImGui::SameLine();
            if (AegisButton("Export Bundle", ImVec2(150.0f, 38.0f), false))
                ExportDiagnosticBundle();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(166.0f, 38.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            if (!export_status_.empty())
                TextGreen(export_status_.c_str());

            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Source Status", ProviderHealthRows(), half, 310.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Data Trust", DataTrustRows(), half, 310.0f);
            RenderInfoGridCard("External Adapters", DataAdapterRows(), half, 350.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Refresh Telemetry", RefreshTelemetryRows(), half, 350.0f);
            RenderInfoGridCard("What Changed", RefreshChangeRows(), 0, 330.0f);
            RenderInfoGridCard("Source Refresh Ledger", ProviderRefreshRows(), 0, 360.0f);
            RenderInfoGridCard("Startup Integrity", startup_integrity_, 0, 240.0f);
            RenderInfoGridCard("Adapter Schema Contracts", aegis::OptionalFeedSchemaRows(), 0, 330.0f);
            RenderInfoGridCard("Per-Sport Odds Status", state_.provider_sports, 0, 360.0f);
            RenderInfoGridCard("Odds Matching Diagnostics", state_.diagnostics, 0, 430.0f);
            RenderInfoGridCard("Health History", ProviderHealthHistoryRows(), 0, 360.0f);
            ImGui::EndChild();
        }

        void RenderProps(float)
        {
            ImGui::BeginChild("props_workspace", ImVec2(0, 0), true);
            CardHeader("Player Props", config_.player_props_enabled ? "Enabled workspace" : "Optional workspace", active_view_);
            if (AegisButton("Enable Props", ImVec2(128.0f, 38.0f), config_.player_props_enabled))
            {
                settings_player_props_enabled_ = true;
                config_.player_props_enabled = true;
                aegis::SaveConfig(config_);
                status_ = "Player props workspace enabled. Configure a prop feed URL or Odds API support in Settings.";
            }
            ImGui::SameLine();
            if (AegisButton("Open Settings", ImVec2(136.0f, 38.0f), false))
                active_view_ = View::Settings;
            ImGui::SameLine();
            if (AegisButton("Refresh Props", ImVec2(136.0f, 38.0f), false))
                BeginSportsRefresh(false, "Prop workspace refresh", "Refreshing the direct sports board before prop review.");
            ImGui::Spacing();
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Prop Feed Status", PlayerPropRows(), half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Prop Risk Rules", PropRiskRows(), half, 300.0f);
            RenderPropCandidatePanel();
            ImGui::EndChild();
        }

        void RenderPropCandidatePanel()
        {
            ImGui::BeginChild("prop_candidates", ImVec2(0, 360.0f), true);
            CardHeader("Prop Candidates", "Requires a verified prop source before values are shown", active_view_);
            if (aegis::Trim(config_.props_feed_url).empty() && aegis::Trim(config_.odds_api_key).empty())
            {
                EmptyState("No prop feed configured", "Add a player prop provider URL or compatible Odds API setup before Aegis displays player-level markets.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("prop_candidates_table", 6, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Matchup", ImGuiTableColumnFlags_WidthStretch, 1.25f);
                ImGui::TableSetupColumn("League", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Status", ImGuiTableColumnFlags_WidthFixed, 95.0f);
                ImGui::TableSetupColumn("Feed", ImGuiTableColumnFlags_WidthStretch, 0.95f);
                ImGui::TableSetupColumn("Read", ImGuiTableColumnFlags_WidthStretch, 1.1f);
                ImGui::TableSetupColumn("Action", ImGuiTableColumnFlags_WidthFixed, 82.0f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (const aegis::Game* game : VisibleGames())
                {
                    if (rendered++ >= 12)
                        break;
                    ImGui::PushID(game->id.c_str());
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(game->matchup.c_str());
                    ImGui::TableSetColumnIndex(1); TextGreen(game->league.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(game->status_label.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(aegis::Trim(config_.props_feed_url).empty() ? "Odds provider" : "Configured URL");
                    ImGui::TableSetColumnIndex(4); TextMuted("Prop adapter is ready, but player markets are hidden until the configured provider returns verified prop rows.");
                    ImGui::TableSetColumnIndex(5);
                    if (PlainLinkButton("Details"))
                        OpenGameDetail(game->id);
                    ImGui::PopID();
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderAlerts(float)
        {
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Alert Rules", AlertRuleRows(), half, 250.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Notification Summary", NotificationSummaryRows(), half, 250.0f);
            RenderNotificationJournalPanel();
            RenderCurrentAlertsPanel();
        }

        void RenderNotificationJournalPanel()
        {
            const std::vector<aegis::InfoItem> rows = ReadNotificationItems(120);
            ImGui::BeginChild("notification_journal", ImVec2(0, 360.0f), true);
            CardHeader("Notification Journal", "Local alert history", active_view_);
            if (AegisButton("Clear Alerts", ImVec2(130.0f, 36.0f), false))
                ClearNotificationJournal();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 36.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::Spacing();
            if (rows.empty())
            {
                EmptyState("No notifications yet", "The journal records high-confidence picks, watched-market changes, and line movement.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("notification_rows", 5, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Time", ImGuiTableColumnFlags_WidthFixed, 72.0f);
                ImGui::TableSetupColumn("Kind", ImGuiTableColumnFlags_WidthFixed, 122.0f);
                ImGui::TableSetupColumn("Title", ImGuiTableColumnFlags_WidthStretch, 1.15f);
                ImGui::TableSetupColumn("Value", ImGuiTableColumnFlags_WidthFixed, 98.0f);
                ImGui::TableSetupColumn("Detail", ImGuiTableColumnFlags_WidthStretch, 1.2f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (const aegis::InfoItem& row : rows)
                {
                    if (rendered++ >= 18)
                        break;
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(row.time.c_str());
                    ImGui::TableSetColumnIndex(1); TextGreen(row.tag.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(row.name.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(row.value.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(row.detail.c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderCurrentAlertsPanel()
        {
            ImGui::BeginChild("current_alerts_panel", ImVec2(0, 300.0f), true);
            CardHeader("Current Board Alerts", "Latest refresh signals", active_view_);
            if (state_.alerts.empty())
            {
                EmptyState("No active alerts", "Aegis will surface line movement, watched-market changes, and high-signal board states here.");
                ImGui::EndChild();
                return;
            }
            RenderInfoList(state_.alerts, 8);
            ImGui::EndChild();
        }

        void RenderExposure(float)
        {
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Exposure Summary", ExposureSummaryRows(), half, 250.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Ticket Guardrails", ResponsibleModeRows(), half, 250.0f);
            RenderExposureLedgerPanel();
        }

        void RenderExposureLedgerPanel()
        {
            const std::vector<ExposureRow> rows = ReadExposureRows(160);
            ImGui::BeginChild("exposure_ledger", ImVec2(0, 380.0f), true);
            CardHeader("Exposure Ledger", "Paper tickets and manual handoffs", active_view_);
            if (AegisButton("Reset Exposure", ImVec2(148.0f, 36.0f), false))
                ClearExposureLedger();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 36.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::Spacing();
            if (rows.empty())
            {
                EmptyState("No exposure entries", "Paper submissions and manual provider handoffs will appear here after ticket preview.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("exposure_rows", 5, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Date", ImGuiTableColumnFlags_WidthFixed, 105.0f);
                ImGui::TableSetupColumn("Mode", ImGuiTableColumnFlags_WidthFixed, 130.0f);
                ImGui::TableSetupColumn("Amount", ImGuiTableColumnFlags_WidthFixed, 96.0f);
                ImGui::TableSetupColumn("Matchup", ImGuiTableColumnFlags_WidthStretch, 1.2f);
                ImGui::TableSetupColumn("Game ID", ImGuiTableColumnFlags_WidthStretch, 0.7f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (auto it = rows.rbegin(); it != rows.rend() && rendered < 18; ++it, ++rendered)
                {
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(it->date.c_str());
                    ImGui::TableSetColumnIndex(1); TextGreen(it->mode.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(FormatMoneyText(it->amount).c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(it->matchup.c_str());
                    ImGui::TableSetColumnIndex(4); TextMuted(it->game_id.c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderGameDetail(float width)
        {
            const aegis::Game* game = selected_game_id_.empty() ? nullptr : GameById(selected_game_id_);
            if (game == nullptr && !state_.games.empty())
            {
                selected_game_id_ = state_.games.front().id;
                game = &state_.games.front();
            }
            ImGui::BeginChild("game_detail", ImVec2(0, 0), true);
            if (game == nullptr)
            {
                CardHeader("Game Detail", "No selected matchup", View::Live);
                EmptyState("No matchup selected", "Open a game from the live board to inspect source data, markets, and model reasoning.");
                ImGui::EndChild();
                return;
            }

            const aegis::Prediction* prediction = PredictionByGameId(game->id);
            CardHeader("Game Detail", game->status_label.c_str(), View::Live);
            if (AegisButton("Back to Board", ImVec2(132.0f, 36.0f), false))
                active_view_ = View::Live;
            ImGui::SameLine();
            if (prediction != nullptr && AegisButton("AI Breakdown", ImVec2(142.0f, 36.0f), true))
                OpenGamePrediction(game->id);
            ImGui::SameLine();
            if (prediction != nullptr && AegisButton("Scenario Lab", ImVec2(132.0f, 36.0f), false))
            {
                selected_prediction_ = static_cast<int>(prediction - state_.predictions.data());
                selected_game_id_ = game->id;
                active_view_ = View::Scenario;
            }
            ImGui::SameLine();
            if (AegisButton(IsWatched(game->id) ? "Watching" : "Watch", ImVec2(110.0f, 36.0f), IsWatched(game->id)))
                AddWatch(game->id);
            ImGui::SameLine();
            const std::string away_track = "Track " + (game->away.abbr.empty() ? game->away.short_name : game->away.abbr);
            if (AegisButton(away_track.c_str(), ImVec2(104.0f, 36.0f), GameMatchesFavorites(*game)))
                AddFavoriteTeam(game->away.name);
            ImGui::SameLine();
            const std::string home_track = "Track " + (game->home.abbr.empty() ? game->home.short_name : game->home.abbr);
            if (AegisButton(home_track.c_str(), ImVec2(104.0f, 36.0f), GameMatchesFavorites(*game)))
                AddFavoriteTeam(game->home.name);

            ImGui::Spacing();
            const float half = std::max(320.0f, (width - 42.0f) * 0.5f);
            ImGui::BeginChild("detail_scoreboard", ImVec2(half, 276.0f), true);
            TextGreen(game->league.c_str());
            ImGui::PushFont(g_font_title);
            ImGui::TextWrapped("%s", game->matchup.c_str());
            ImGui::PopFont();
            ImGui::Text("Status: %s", game->detail.empty() ? game->status_label.c_str() : game->detail.c_str());
            if (!game->venue.empty())
                ImGui::Text("Venue: %s", game->venue.c_str());
            ImGui::Separator();
            TeamRow(game->away);
            TeamRow(game->home);
            ImGui::Separator();
            ImGui::Text("Spread: %s / %s", game->spread_favorite.c_str(), game->spread_other.c_str());
            ImGui::Text("Total: %s / %s", game->total_over.c_str(), game->total_under.c_str());
            RenderStateBadge(SourceBadgeText(*game));
            ImGui::SameLine();
            TextMuted(FirstNonEmpty(game->source_timestamp, game->feed_age_label, "Freshness unknown").c_str());
            if (!game->odds_match_status.empty())
                TextMuted(("Odds match: " + game->odds_match_status).c_str());
            if (!game->odds_match_detail.empty())
                TextMuted(game->odds_match_detail.c_str());
            if (!game->source_note.empty())
                TextMuted(game->source_note.c_str());
            ImGui::EndChild();

            ImGui::SameLine();
            ImGui::BeginChild("detail_model", ImVec2(0, 276.0f), true);
            if (prediction == nullptr)
            {
                EmptyState("No model row yet", "This matchup is loaded, but the configured model row limit did not include it.");
            }
            else
            {
                TextGreen(prediction->market.c_str());
                ImGui::PushFont(g_font_title);
                ImGui::TextWrapped("%s", prediction->pick.c_str());
                ImGui::PopFont();
                ImGui::TextWrapped("%s", prediction->reason.c_str());
                ImGui::Separator();
                RenderDrawerScore("Confidence", prediction->confidence, "Current local model estimate.");
                ImGui::SameLine();
                RenderDrawerScore("Edge", prediction->edge, prediction->risk.c_str());
                ImGui::SameLine();
                RenderDrawerScore("Inputs", std::to_string(prediction->input_count), "Counted direct-source inputs.");
                TextMuted(("Trust: " + FirstNonEmpty(prediction->data_trust, "Pending", "") + " / " + FirstNonEmpty(prediction->confidence_band, "Unbanded", "")).c_str());
            }
            ImGui::EndChild();

            ImGui::Spacing();
            ImGui::BeginChild("detail_markets", ImVec2(0, 270.0f), true);
            CardHeader("Market Lines", "Provider comparison and movement", View::Arbitrage);
            if (game->bet_links.empty())
            {
                EmptyState("No provider links yet", "Save an Odds API key or wait for this matchup to appear in the exchange/search feeds.");
            }
            else if (ImGui::BeginTable("detail_market_table", 7, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Provider", ImGuiTableColumnFlags_WidthStretch, 1.15f);
                ImGui::TableSetupColumn("Kind", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Market", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableSetupColumn("Line", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Price", ImGuiTableColumnFlags_WidthFixed, 82.0f);
                ImGui::TableSetupColumn("Movement", ImGuiTableColumnFlags_WidthStretch, 0.85f);
                ImGui::TableSetupColumn("Source", ImGuiTableColumnFlags_WidthStretch, 0.85f);
                ImGui::TableHeadersRow();
                for (const aegis::BetLink& link : game->bet_links)
                {
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(FirstNonEmpty(link.title, link.provider_key, "Provider").c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::TextUnformatted(link.kind.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(link.market.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(link.line.c_str());
                    ImGui::TableSetColumnIndex(4); TextGreen(link.price.empty() ? "--" : link.price.c_str());
                    ImGui::TableSetColumnIndex(5); ImGui::TextUnformatted(FirstNonEmpty(link.movement, link.last_update, link.source, "").c_str());
                    ImGui::TableSetColumnIndex(6);
                    RenderStateBadge(LinkBadgeText(link));
                    TextMuted(FirstNonEmpty(link.source, link.last_update, link.note, "").c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();

            if (prediction != nullptr)
            {
                ImGui::Spacing();
                ImGui::BeginChild("detail_explain", ImVec2(0, 360.0f), true);
                CardHeader("Prediction Explainability", "Inputs, penalties, and comparison", View::Picks);
                RenderTeamComparison(prediction->comparison);
                ImGui::SeparatorText("Build Steps");
                RenderInfoList(prediction->steps, 8);
                ImGui::EndChild();
            }
            ImGui::EndChild();
        }

        void RenderAnalytics(float)
        {
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Model Blend", state_.factors, half, 255.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Coverage Universe", state_.coverage, half, 255.0f);
            RenderInfoGridCard("Market Health", state_.providers, half, 255.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Market Intelligence", state_.books, half, 255.0f);
            RenderInfoGridCard("Provider Health", ProviderHealthRows(), 0, 250.0f);
            RenderInfoGridCard("Data Trust", DataTrustRows(), half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Notification Center", NotificationRows(), half, 300.0f);
            RenderInfoGridCard("Performance", state_.performance, half, 330.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Board Metrics", state_.metrics, half, 330.0f);
            RenderInfoGridCard("Model Audit", ModelAuditRows(), 0, 250.0f);
            RenderInfoGridCard("Model Calibration", CalibrationSummaryRows(), half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Provider Quality", ProviderQualitySummaryRows(), half, 300.0f);
            RenderInfoGridCard("Odds Matching Diagnostics", state_.diagnostics, 0, 360.0f);
            RenderInfoGridCard("Per-Sport Odds Status", state_.provider_sports, 0, 360.0f);
            if (config_.bankroll_analytics_enabled)
                RenderInfoGridCard("Bankroll Analytics", BankrollRows(), 0, 280.0f);
            RenderCalibrationPanel();
            RenderProviderQualityPanel();
            RenderProviderSportQualityPanel();
            RenderBacktestPanel();
            RenderAuditHistoryPanel();
            RenderDiagnosticsPanel();
        }

        void RenderArbitrage(float)
        {
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            RenderInfoGridCard("Opportunity Scanner", state_.opportunities, half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Edge Engine", state_.edge_stack, half, 300.0f);
            RenderInfoGridCard("Market Guardrails", state_.rules, 0, 300.0f);
            RenderMarketAccessBoard();
            RenderMarketHistoryPanel();
        }

        void RenderReportFilters(float width)
        {
            ImGui::BeginChild("report_filters", ImVec2(0, 158.0f), true, ImGuiWindowFlags_NoScrollbar);
            CardHeader("Export Filters", "Sport, date, provider, market, and watchlist scope", active_view_);

            const char* sport_labels[] = {
                "All Sports", "Live Now", "Upcoming", "NBA", "WNBA", "NCAAB", "NCAAW", "NFL", "College Football", "UFL",
                "MLB", "NCAA Baseball", "NCAA Softball", "NHL", "NCAA Hockey", "Soccer", "MLS", "Combat Sports", "Tennis",
                "Golf", "Racing", "Cricket", "Rugby", "Lacrosse", "Volleyball", "Esports"
            };
            const char* sport_values[] = {
                "all", "live", "scheduled", "league:nba", "league:wnba", "league:ncaab", "league:ncaaw", "league:nfl", "league:ncaaf", "league:ufl",
                "league:mlb", "league:college-baseball", "league:college-softball", "league:nhl", "league:ncaa-hockey", "group:soccer", "league:mls", "group:combat", "group:tennis",
                "group:golf", "group:racing", "group:cricket", "group:rugby", "group:lacrosse", "group:volleyball", "group:esports"
            };
            const char* date_labels[] = { "All dates", "Today", "Next 7 days", "Upcoming only", "Finals only" };
            const char* provider_labels[] = { "All providers", "Sportsbook lines", "Kalshi/exchange", "Any priced line", "No priced line" };
            const char* market_labels[] = { "All markets", "Spread", "Total", "Moneyline", "Props", "Unpriced only" };

            const float col = std::max(142.0f, (width - 74.0f) / 5.0f);
            ImGui::PushItemWidth(col);
            ImGui::Combo("Sport", &report_sport_filter_, sport_labels, IM_ARRAYSIZE(sport_labels));
            ImGui::SameLine();
            ImGui::Combo("Date", &report_date_filter_, date_labels, IM_ARRAYSIZE(date_labels));
            ImGui::SameLine();
            ImGui::Combo("Provider", &report_provider_filter_, provider_labels, IM_ARRAYSIZE(provider_labels));
            ImGui::SameLine();
            ImGui::Combo("Market", &report_market_filter_, market_labels, IM_ARRAYSIZE(market_labels));
            ImGui::PopItemWidth();
            ImGui::SameLine();
            ImGui::BeginGroup();
            ImGui::Checkbox("Watchlist only", &report_watchlist_only_);
            if (PlainLinkButton("Clear"))
            {
                report_sport_filter_ = 0;
                report_date_filter_ = 0;
                report_provider_filter_ = 0;
                report_market_filter_ = 0;
                report_watchlist_only_ = false;
                report_league_filter_[0] = '\0';
            }
            ImGui::EndGroup();

            ImGui::SetNextItemWidth(std::min(width * 0.42f, 420.0f));
            ImGui::InputTextWithHint("League/team", "NBA, Chiefs, KC, MLS...", report_league_filter_, sizeof(report_league_filter_));
            ImGui::SameLine();
            if (AegisButton("Use Board Filters", ImVec2(156.0f, 36.0f), false))
            {
                for (int i = 0; i < IM_ARRAYSIZE(sport_values); ++i)
                {
                    if (active_filter_ == sport_values[i])
                    {
                        report_sport_filter_ = i;
                        break;
                    }
                }
                std::snprintf(report_league_filter_, sizeof(report_league_filter_), "%s", search_);
                report_watchlist_only_ = filter_watchlist_only_;
                report_provider_filter_ = filter_market_lines_only_ ? 3 : 0;
                report_market_filter_ = filter_market_lines_only_ ? 0 : report_market_filter_;
            }
            ImGui::SameLine();
            TextMuted(ReportFilterSummary().c_str());

            const int game_count = static_cast<int>(ReportFilteredGames().size());
            const int pick_count = static_cast<int>(ReportFilteredPredictions().size());
            const int market_count = static_cast<int>(ReportFilteredMarketPairs(1000).size());
            TextMuted(("Export scope: " + std::to_string(game_count) + " games / " + std::to_string(pick_count) + " picks / " + std::to_string(market_count) + " tracked markets").c_str());
            ImGui::EndChild();
        }

        void RenderReports(float width)
        {
            const float half = (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f;
            ImGui::BeginChild("reports_header", ImVec2(0, 128.0f), true, ImGuiWindowFlags_NoScrollbar);
            CardHeader("Report Center", "Daily operating picture and local exports", active_view_);
            if (AegisButton("Export Workspace", ImVec2(164.0f, 38.0f), true))
                ExportWorkspaceReport();
            ImGui::SameLine();
            if (AegisButton("Export CSV", ImVec2(122.0f, 38.0f), false))
                ExportCsvReport();
            ImGui::SameLine();
            if (AegisButton("Export PDF", ImVec2(122.0f, 38.0f), false))
                ExportPdfReport();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 38.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::SameLine();
            if (AegisButton("Refresh Report", ImVec2(150.0f, 38.0f), false))
                BeginSportsRefresh(false, "Report refresh", "Updating direct sources before rebuilding report panels.");
            if (!export_status_.empty())
                TextGreen(export_status_.c_str());
            ImGui::EndChild();
            RenderReportFilters(width);
            RenderInfoGridCard("Daily Summary", DailyReportRows(), half, 290.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Market Snapshot Summary", MarketSnapshotSummaryRows(), half, 290.0f);
            RenderInfoGridCard("Model Calibration", CalibrationSummaryRows(), half, 300.0f);
            ImGui::SameLine();
            RenderInfoGridCard("Provider Quality", ProviderQualitySummaryRows(), half, 300.0f);
            RenderInfoGridCard("Scenario CLV", ScenarioClvSummaryRows(), 0, 300.0f);
            if (config_.bankroll_analytics_enabled)
                RenderInfoGridCard("Bankroll Analytics", BankrollRows(), 0, 280.0f);
            RenderScenarioJournalPanel();
            RenderMarketHistoryPanel();
            RenderCalibrationPanel();
            RenderProviderQualityPanel();
            RenderProviderSportQualityPanel();
            RenderBacktestPanel();
            RenderAuditHistoryPanel();
            RenderExposureLedgerPanel();
            RenderNotificationJournalPanel();
        }

        void RenderMarketHistoryPanel()
        {
            const std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> pairs = MarketSnapshotPairs(1000);
            ImGui::BeginChild("market_history", ImVec2(0, 380.0f), true);
            CardHeader("Market Movement History", "Local sportsbook snapshot ledger", active_view_);
            if (AegisButton("Export Workspace", ImVec2(164.0f, 36.0f), false))
                ExportWorkspaceReport();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 36.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::Spacing();
            if (pairs.empty())
            {
                EmptyState("No market snapshots yet", "Matched sportsbook lines will appear here after an odds provider returns comparable markets.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("market_snapshot_rows", 8, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Seen", ImGuiTableColumnFlags_WidthFixed, 64.0f);
                ImGui::TableSetupColumn("Matchup", ImGuiTableColumnFlags_WidthStretch, 1.25f);
                ImGui::TableSetupColumn("Book", ImGuiTableColumnFlags_WidthFixed, 110.0f);
                ImGui::TableSetupColumn("Open Line", ImGuiTableColumnFlags_WidthStretch, 0.85f);
                ImGui::TableSetupColumn("Open", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableSetupColumn("Current Line", ImGuiTableColumnFlags_WidthStretch, 0.85f);
                ImGui::TableSetupColumn("Current", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableSetupColumn("Move", ImGuiTableColumnFlags_WidthFixed, 110.0f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (const auto& pair : pairs)
                {
                    if (rendered++ >= 18)
                        break;
                    const MarketSnapshotRow& first = pair.first;
                    const MarketSnapshotRow& last = pair.second;
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(last.seen_at.c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::TextUnformatted(last.matchup.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(last.book.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(first.line.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(first.price.c_str());
                    ImGui::TableSetColumnIndex(5); ImGui::TextUnformatted(last.line.c_str());
                    ImGui::TableSetColumnIndex(6); TextGreen(last.price.c_str());
                    ImGui::TableSetColumnIndex(7);
                    const std::string movement = SnapshotMoveLabel(first, last);
                    if (movement == "No movement")
                        TextMuted(movement.c_str());
                    else
                        TextGreen(movement.c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderCalibrationPanel()
        {
            const std::vector<CalibrationBucket> buckets = BuildCalibrationBuckets();
            ImGui::BeginChild("calibration_panel", ImVec2(0, 320.0f), true);
            CardHeader("Confidence Calibration", "How model confidence grades locally", active_view_);
            if (buckets.empty())
            {
                EmptyState("No calibration samples", "Prediction audit rows will populate confidence bands after refreshes and final games.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("calibration_rows", 6, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Band", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Samples", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Graded", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableSetupColumn("Win Rate", ImGuiTableColumnFlags_WidthFixed, 100.0f);
                ImGui::TableSetupColumn("Avg Conf", ImGuiTableColumnFlags_WidthFixed, 100.0f);
                ImGui::TableSetupColumn("Read", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableHeadersRow();
                for (const CalibrationBucket& bucket : buckets)
                {
                    const std::string win_rate = bucket.graded > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.wins) * 100.0 / bucket.graded))) + "%" : "--";
                    const std::string avg_conf = bucket.samples > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.confidence_sum) / bucket.samples))) + "%" : "--";
                    const std::string read = bucket.graded == 0 ? "Awaiting finals" :
                        (bucket.wins * 100 >= bucket.graded * std::max(52, bucket.samples > 0 ? bucket.confidence_sum / bucket.samples - 8 : 52) ? "Tracking well" : "Needs calibration");
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); TextGreen(bucket.label.c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::Text("%d", bucket.samples);
                    ImGui::TableSetColumnIndex(2); ImGui::Text("%d", bucket.graded);
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(win_rate.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(avg_conf.c_str());
                    ImGui::TableSetColumnIndex(5); TextMuted(read.c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderProviderQualityPanel()
        {
            const std::vector<ProviderQuality> rows = BuildProviderQualityRows();
            ImGui::BeginChild("provider_quality_panel", ImVec2(0, 320.0f), true);
            CardHeader("Provider Quality Board", "Book coverage and movement contribution", active_view_);
            if (rows.empty())
            {
                EmptyState("No provider quality yet", "Provider quality appears after sportsbook links or market snapshots are available.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("provider_quality_rows", 6, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Provider", ImGuiTableColumnFlags_WidthStretch, 1.2f);
                ImGui::TableSetupColumn("Live Lines", ImGuiTableColumnFlags_WidthFixed, 96.0f);
                ImGui::TableSetupColumn("Snapshots", ImGuiTableColumnFlags_WidthFixed, 100.0f);
                ImGui::TableSetupColumn("Moved", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableSetupColumn("Games", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableSetupColumn("Score", ImGuiTableColumnFlags_WidthFixed, 90.0f);
                ImGui::TableHeadersRow();
                for (const ProviderQuality& row : rows)
                {
                    const int score = std::clamp(row.live_lines * 4 + row.snapshots + row.moved * 5 + row.games * 3, 0, 100);
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); TextGreen(row.book.c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::Text("%d", row.live_lines);
                    ImGui::TableSetColumnIndex(2); ImGui::Text("%d", row.snapshots);
                    ImGui::TableSetColumnIndex(3); ImGui::Text("%d", row.moved);
                    ImGui::TableSetColumnIndex(4); ImGui::Text("%d", row.games);
                    ImGui::TableSetColumnIndex(5); ImGui::Text("%d/100", score);
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderProviderSportQualityPanel()
        {
            const std::vector<aegis::InfoItem> rows = ProviderSportQualityRows();
            ImGui::BeginChild("provider_sport_quality_panel", ImVec2(0, 340.0f), true);
            CardHeader("Provider Quality by Sport", "Coverage by book and league group", active_view_);
            if (rows.empty())
            {
                EmptyState("No provider-sport rows yet", "Provider and sport quality appears after direct odds links or market snapshots are available.");
                ImGui::EndChild();
                return;
            }
            const float cell_w = std::max(220.0f, (ImGui::GetContentRegionAvail().x - 12.0f) * 0.5f);
            for (size_t i = 0; i < rows.size(); ++i)
            {
                if (i % 2 != 0)
                    ImGui::SameLine();
                RenderInfoChip(rows[i], ImVec2(cell_w, 96.0f));
            }
            ImGui::EndChild();
        }

        void RenderBacktestPanel()
        {
            const std::vector<aegis::InfoItem> rows = BacktestRowsByMarket();
            ImGui::BeginChild("backtest_panel", ImVec2(0, 320.0f), true);
            CardHeader("Backtest by Market", "Local graded audit samples", active_view_);
            if (rows.empty())
            {
                EmptyState("No backtest rows yet", "Final games will grade model reads after enough local prediction samples are recorded.");
                ImGui::EndChild();
                return;
            }
            const float cell_w = std::max(220.0f, (ImGui::GetContentRegionAvail().x - 12.0f) * 0.5f);
            for (size_t i = 0; i < rows.size(); ++i)
            {
                if (i % 2 != 0)
                    ImGui::SameLine();
                RenderInfoChip(rows[i], ImVec2(cell_w, 96.0f));
            }
            ImGui::EndChild();
        }

        void RenderScenario(float width)
        {
            (void)width;
            const int fallback_index = ScenarioPredictionIndex();
            if ((selected_prediction_ < 0 || selected_prediction_ >= static_cast<int>(state_.predictions.size())) && fallback_index >= 0)
            {
                selected_prediction_ = fallback_index;
                selected_game_id_ = state_.predictions[static_cast<size_t>(fallback_index)].game_id;
            }

            const aegis::Prediction* prediction = ScenarioPrediction();
            const aegis::Game* game = ScenarioGame(prediction);
            std::vector<ScenarioLine> lines = ScenarioLines(prediction, game);
            scenario_stake_ = std::clamp(scenario_stake_, 1.0, 100000.0);
            const int probability = ScenarioProbability(prediction);
            std::sort(lines.begin(), lines.end(), [this, probability](const ScenarioLine& left, const ScenarioLine& right) {
                const double left_ev = HasAmericanOddsText(left.link.price) ? ScenarioExpectedProfit(left.link.price, probability, scenario_stake_) : -DBL_MAX;
                const double right_ev = HasAmericanOddsText(right.link.price) ? ScenarioExpectedProfit(right.link.price, probability, scenario_stake_) : -DBL_MAX;
                if (std::fabs(left_ev - right_ev) > 0.001)
                    return left_ev > right_ev;
                return FirstNonEmpty(left.link.title, left.link.provider_key, left.link.kind, "Provider") < FirstNonEmpty(right.link.title, right.link.provider_key, right.link.kind, "Provider");
            });

            const ScenarioLine* best = nullptr;
            for (const ScenarioLine& line : lines)
            {
                if (HasAmericanOddsText(line.link.price))
                {
                    best = &line;
                    break;
                }
            }

            ImGui::BeginChild("scenario_lab", ImVec2(0, 0), true);
            CardHeader("Scenario Lab", "Stake sizing, best-line comparison, and manual guardrails", active_view_);
            if (AegisButton("Refresh Lines", ImVec2(136.0f, 36.0f), false))
                BeginSportsRefresh(false, "Refreshing scenario lines", "Rebuilding the lab from direct scoreboard and odds providers.");
            ImGui::SameLine();
            if (AegisButton("Reset Probability", ImVec2(154.0f, 36.0f), false))
                scenario_probability_override_ = 0;
            ImGui::SameLine();
            if (AegisButton("Open Reports", ImVec2(128.0f, 36.0f), false))
                active_view_ = View::Reports;
            ImGui::SameLine();
            if (prediction != nullptr && AegisButton("Save Read", ImVec2(118.0f, 36.0f), true))
                AppendScenarioJournal(*prediction, best, probability);
            ImGui::Spacing();

            if (prediction == nullptr)
            {
                EmptyState("No scenario pick available", "Refresh the direct sports board or widen the filters to load a model pick.");
                ImGui::EndChild();
                return;
            }

            const float half = std::max(360.0f, (ImGui::GetContentRegionAvail().x - 16.0f) * 0.5f);
            ImGui::BeginChild("scenario_controls", ImVec2(half, 332.0f), true);
            CardHeader("Scenario Controls", prediction->league.c_str(), View::Scenario);
            RenderScenarioPickCombo();
            ImGui::PushItemWidth(180.0f);
            ImGui::InputDouble("Stake", &scenario_stake_, 5.0, 25.0, "$%.2f");
            ImGui::PopItemWidth();
            int edited_probability = probability;
            ImGui::PushItemWidth(220.0f);
            if (ImGui::SliderInt("Assumed probability", &edited_probability, 1, 99, "%d%%"))
                scenario_probability_override_ = edited_probability;
            ImGui::PopItemWidth();
            TextMuted(scenario_probability_override_ > 0 ? "Scenario probability is math-only; stored model confidence is unchanged." : "Using current model confidence for scenario math.");
            ImGui::Separator();
            ImGui::TextWrapped("%s", prediction->matchup.c_str());
            TextGreen(prediction->pick.c_str());
            TextMuted(ScenarioGuardrailLabel(prediction).c_str());
            ImGui::Spacing();
            if (AegisButton("Add To Slip", ImVec2(128.0f, 34.0f), ScenarioCanStage(prediction)))
            {
                if (ScenarioCanStage(prediction))
                {
                    preview_amount_ = scenario_stake_;
                    AddWatch(prediction->game_id);
                    active_view_ = View::Watchlist;
                    status_ = "Scenario staged in the controlled slip preview.";
                }
                else
                {
                    status_ = ScenarioGuardrailLabel(prediction);
                }
            }
            ImGui::SameLine();
            if (AegisButton(IsWatched(prediction->game_id) ? "Watching" : "Watch", ImVec2(104.0f, 34.0f), IsWatched(prediction->game_id)))
                AddWatch(prediction->game_id);
            ImGui::SameLine();
            if (best != nullptr && AegisButton("Open Best", ImVec2(112.0f, 34.0f), !best->link.url.empty()))
                OpenScenarioProvider(*best);
            ImGui::SameLine();
            if (AegisButton("Save Read", ImVec2(112.0f, 34.0f), true))
                AppendScenarioJournal(*prediction, best, probability);
            ImGui::EndChild();

            ImGui::SameLine();
            ImGui::BeginChild("scenario_summary", ImVec2(0, 332.0f), true);
            CardHeader("Scenario Readout", best == nullptr ? "No priced line yet" : FirstNonEmpty(best->link.title, best->link.provider_key, best->link.kind, "Provider").c_str(), View::Scenario);
            const std::vector<aegis::InfoItem> summary = ScenarioSummaryRows(prediction, best, probability);
            const float cell_w = std::max(190.0f, (ImGui::GetContentRegionAvail().x - 12.0f) * 0.5f);
            for (size_t i = 0; i < summary.size(); ++i)
            {
                if (i % 2 != 0)
                    ImGui::SameLine();
                RenderInfoChip(summary[i], ImVec2(cell_w, 96.0f));
            }
            ImGui::EndChild();

            ImGui::Spacing();
            ImGui::BeginChild("scenario_lines", ImVec2(0, 390.0f), true);
            CardHeader("Best Line Comparison", "Expected return by provider at the current scenario stake", View::Scenario);
            if (lines.empty())
            {
                EmptyState("No comparable lines yet", "Save an Odds API key or wait for direct providers to return lines for this matchup.");
                ImGui::EndChild();
                RenderScenarioJournalPanel();
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("scenario_line_rows", 8, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Provider", ImGuiTableColumnFlags_WidthStretch, 1.2f);
                ImGui::TableSetupColumn("Market", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableSetupColumn("Line", ImGuiTableColumnFlags_WidthFixed, 82.0f);
                ImGui::TableSetupColumn("Price", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableSetupColumn("Implied", ImGuiTableColumnFlags_WidthFixed, 82.0f);
                ImGui::TableSetupColumn("To Win", ImGuiTableColumnFlags_WidthFixed, 88.0f);
                ImGui::TableSetupColumn("EV", ImGuiTableColumnFlags_WidthFixed, 88.0f);
                ImGui::TableSetupColumn("Action", ImGuiTableColumnFlags_WidthFixed, 94.0f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (const ScenarioLine& line : lines)
                {
                    if (rendered++ >= 18)
                        break;
                    const bool priced = HasAmericanOddsText(line.link.price);
                    const double to_win = priced ? aegis::ToWin(line.link.price, scenario_stake_) : 0.0;
                    const double expected = priced ? ScenarioExpectedProfit(line.link.price, probability, scenario_stake_) : 0.0;
                    const double implied = priced ? 100.0 / aegis::DecimalFromAmerican(line.link.price) : 0.0;
                    ImGui::PushID((line.link.provider_key + line.link.title + line.link.market + line.link.line + line.link.price + std::to_string(rendered)).c_str());
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0);
                    TextGreen(FirstNonEmpty(line.link.title, line.link.provider_key, line.link.kind, "Provider").c_str());
                    TextMuted(FirstNonEmpty(line.link.movement, line.link.last_update, line.source, "").c_str());
                    ImGui::TableSetColumnIndex(1);
                    ImGui::TextUnformatted(FirstNonEmpty(line.link.market, prediction->market, "Market").c_str());
                    TextMuted(FirstNonEmpty(line.link.model_edge, line.link.fair_odds, line.link.book_probability, "").c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(line.link.line.empty() ? "--" : line.link.line.c_str());
                    ImGui::TableSetColumnIndex(3); TextGreen(line.link.price.empty() ? "--" : line.link.price.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(priced ? (std::to_string(static_cast<int>(std::round(implied))) + "%").c_str() : "--");
                    ImGui::TableSetColumnIndex(5); ImGui::TextUnformatted(priced ? FormatMoneyText(to_win).c_str() : "--");
                    ImGui::TableSetColumnIndex(6);
                    if (priced && expected >= 0.0)
                        TextGreen(FormatSignedMoneyText(expected).c_str());
                    else if (priced)
                        ImGui::TextColored(V4(1.0f, 0.45f, 0.35f, 1.0f), "%s", FormatSignedMoneyText(expected).c_str());
                    else
                        TextMuted("--");
                    ImGui::TableSetColumnIndex(7);
                    if (PlainLinkButton(line.link.url.empty() ? "No link" : "Open"))
                        OpenScenarioProvider(line);
                    ImGui::PopID();
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
            RenderScenarioJournalPanel();
            ImGui::EndChild();
        }

        void RenderScenarioPickCombo()
        {
            std::vector<int> indexes = VisiblePredictionIndexes();
            if (indexes.empty())
            {
                for (int i = 0; i < static_cast<int>(state_.predictions.size()); ++i)
                    indexes.push_back(i);
            }
            if (indexes.empty())
                return;

            int current_pos = 0;
            bool found_selected = false;
            for (int i = 0; i < static_cast<int>(indexes.size()); ++i)
            {
                if (indexes[static_cast<size_t>(i)] == selected_prediction_)
                {
                    current_pos = i;
                    found_selected = true;
                    break;
                }
            }
            if (!found_selected)
            {
                selected_prediction_ = indexes.front();
                selected_game_id_ = state_.predictions[static_cast<size_t>(selected_prediction_)].game_id;
                scenario_probability_override_ = 0;
            }
            const aegis::Prediction& current = state_.predictions[static_cast<size_t>(indexes[static_cast<size_t>(current_pos)])];
            const std::string preview = ScenarioComboLabel(current);
            ImGui::SetNextItemWidth(-1);
            if (ImGui::BeginCombo("Pick", preview.c_str()))
            {
                for (int i = 0; i < static_cast<int>(indexes.size()); ++i)
                {
                    const int index = indexes[static_cast<size_t>(i)];
                    const aegis::Prediction& pick = state_.predictions[static_cast<size_t>(index)];
                    const bool selected = index == selected_prediction_;
                    ImGui::PushID(index);
                    if (ImGui::Selectable(ScenarioComboLabel(pick).c_str(), selected))
                    {
                        selected_prediction_ = index;
                        selected_game_id_ = pick.game_id;
                        scenario_probability_override_ = 0;
                    }
                    if (selected)
                        ImGui::SetItemDefaultFocus();
                    ImGui::PopID();
                }
                ImGui::EndCombo();
            }
        }

        std::string ScenarioComboLabel(const aegis::Prediction& prediction) const
        {
            return prediction.matchup + " / " + prediction.market + " / " + prediction.confidence;
        }

        const aegis::Prediction* ScenarioPrediction() const
        {
            if (selected_prediction_ >= 0 && selected_prediction_ < static_cast<int>(state_.predictions.size()))
                return &state_.predictions[static_cast<size_t>(selected_prediction_)];
            const int index = ScenarioPredictionIndex();
            return index >= 0 ? &state_.predictions[static_cast<size_t>(index)] : nullptr;
        }

        int ScenarioPredictionIndex() const
        {
            if (selected_prediction_ >= 0 && selected_prediction_ < static_cast<int>(state_.predictions.size()))
                return selected_prediction_;
            const std::vector<int> visible = VisiblePredictionIndexes();
            for (const int index : visible)
            {
                const aegis::Prediction& prediction = state_.predictions[static_cast<size_t>(index)];
                if (prediction.status_key != "final")
                    return index;
            }
            if (!visible.empty())
                return visible.front();
            return state_.predictions.empty() ? -1 : 0;
        }

        const aegis::Game* ScenarioGame(const aegis::Prediction* prediction) const
        {
            if (prediction != nullptr)
            {
                const aegis::Game* game = GameById(prediction->game_id);
                if (game != nullptr)
                    return game;
            }
            return selected_game_id_.empty() ? nullptr : GameById(selected_game_id_);
        }

        int ScenarioProbability(const aegis::Prediction* prediction) const
        {
            const int base = prediction == nullptr ? 50 : prediction->confidence_value;
            return std::clamp(scenario_probability_override_ > 0 ? scenario_probability_override_ : base, 1, 99);
        }

        bool HasAmericanOddsText(const std::string& text) const
        {
            bool has_sign = false;
            int digits = 0;
            for (const char c : text)
            {
                if (c == '+' || c == '-')
                    has_sign = true;
                else if (std::isdigit(static_cast<unsigned char>(c)) != 0)
                    ++digits;
            }
            return has_sign && digits >= 2;
        }

        double ScenarioExpectedProfit(const std::string& odds, int probability, double stake) const
        {
            const double chance = std::clamp(static_cast<double>(probability) / 100.0, 0.01, 0.99);
            return chance * aegis::ToWin(odds, stake) - (1.0 - chance) * std::max(0.0, stake);
        }

        std::string FormatSignedMoneyText(double value) const
        {
            std::ostringstream stream;
            stream << (value >= 0.0 ? "+" : "-") << "$" << std::fixed << std::setprecision(2) << std::fabs(value);
            return stream.str();
        }

        bool ScenarioCanStage(const aegis::Prediction* prediction) const
        {
            if (prediction == nullptr)
                return false;
            if (prediction->status_key == "final")
                return false;
            if (scenario_stake_ > config_.max_ticket_amount)
                return false;
            if (TodayPreviewExposure() + scenario_stake_ > config_.daily_exposure_limit)
                return false;
            if (prediction->confidence_value < config_.min_ticket_confidence)
                return false;
            return true;
        }

        std::string ScenarioGuardrailLabel(const aegis::Prediction* prediction) const
        {
            if (prediction == nullptr)
                return "Select a model pick to run a scenario.";
            if (prediction->status_key == "final")
                return "Blocked: final games are audit-only.";
            if (scenario_stake_ > config_.max_ticket_amount)
                return "Blocked: stake is above the local ticket limit.";
            if (TodayPreviewExposure() + scenario_stake_ > config_.daily_exposure_limit)
                return "Blocked: daily exposure limit would be exceeded.";
            if (prediction->confidence_value < config_.min_ticket_confidence)
                return "Blocked: model confidence is below the configured floor.";
            if (prediction->odds == "--" || prediction->odds == "Feed snapshot")
                return "Monitor: scenario can be saved, but no direct sportsbook price is attached to the pick.";
            return config_.paper_only_mode ? "Ready: can be added to paper slip preview." : "Ready: can be staged for manual provider handoff.";
        }

        std::vector<ScenarioLine> ScenarioLines(const aegis::Prediction* prediction, const aegis::Game* game) const
        {
            std::vector<ScenarioLine> lines;
            std::set<std::string> seen;
            auto add_line = [&](const aegis::BetLink& link, const std::string& source) {
                if (link.market.empty() && link.line.empty() && link.price.empty() && link.title.empty())
                    return;
                const std::string key = aegis::Lower(FirstNonEmpty(link.title, link.provider_key, link.kind, "Provider") + "|" + link.market + "|" + link.line + "|" + link.price + "|" + link.url);
                if (!seen.insert(key).second)
                    return;
                ScenarioLine row;
                row.link = link;
                row.source = source;
                lines.push_back(row);
            };
            if (game != nullptr)
            {
                for (const aegis::BetLink& link : game->bet_links)
                    add_line(link, game->source_note.empty() ? "game provider feed" : game->source_note);
            }
            if (prediction != nullptr)
            {
                for (const aegis::BetLink& link : prediction->market_links)
                    add_line(link, "prediction market feed");
                if (lines.empty() && HasAmericanOddsText(prediction->odds))
                {
                    aegis::BetLink fallback;
                    fallback.title = prediction->best_book.empty() ? "Best available" : prediction->best_book;
                    fallback.market = prediction->market;
                    fallback.line = prediction->book_line;
                    fallback.price = prediction->odds;
                    fallback.available = true;
                    fallback.model_edge = prediction->edge;
                    fallback.fair_odds = prediction->fair_odds;
                    add_line(fallback, "model row");
                }
            }
            return lines;
        }

        std::vector<aegis::InfoItem> ScenarioSummaryRows(const aegis::Prediction* prediction, const ScenarioLine* best, int probability) const
        {
            const std::string odds = best != nullptr ? best->link.price : (prediction == nullptr ? "--" : prediction->odds);
            const bool priced = HasAmericanOddsText(odds);
            const double to_win = priced ? aegis::ToWin(odds, scenario_stake_) : 0.0;
            const double expected = priced ? ScenarioExpectedProfit(odds, probability, scenario_stake_) : 0.0;
            const std::string provider = best == nullptr ? "Waiting" : FirstNonEmpty(best->link.title, best->link.provider_key, best->link.kind, "Provider");
            return {
                {"Selected pick", "", prediction == nullptr ? "--" : prediction->confidence, "", prediction == nullptr ? "No model pick selected." : prediction->pick},
                {"Assumed probability", "", std::to_string(probability) + "%", "", scenario_probability_override_ > 0 ? "Custom scenario probability; stored model confidence is unchanged." : "Current model confidence from the direct-source refresh."},
                {"Stake", "", FormatMoneyText(scenario_stake_), "", "Used only for scenario math and controlled slip preview."},
                {"Best provider", "", provider, "", best == nullptr ? "No provider line available for this selection yet." : FirstNonEmpty(best->link.market, prediction == nullptr ? "" : prediction->market, "Market") + " at " + odds},
                {"Profit if correct", "", priced ? FormatMoneyText(to_win) : "--", "", priced ? "Potential profit before stake at the selected odds." : "Needs an American odds price from a provider."},
                {"Expected return", "", priced ? FormatSignedMoneyText(expected) : "--", "", "Scenario EV uses assumed probability and current provider odds."}
            };
        }

        void OpenScenarioProvider(const ScenarioLine& line)
        {
            if (line.link.url.empty())
            {
                status_ = "This scenario line has no provider URL yet.";
                return;
            }
            status_ = "Opening provider for manual review only. No order is placed by Aegis.";
            aegis::OpenExternalUrl(line.link.url);
        }

        std::filesystem::path ScenarioJournalFile() const
        {
            return aegis::AppDataDirectory() / "scenario-journal.tsv";
        }

        void AppendScenarioJournal(const aegis::Prediction& prediction, const ScenarioLine* best, int probability)
        {
            const std::string provider = best == nullptr ? "No priced provider" : FirstNonEmpty(best->link.title, best->link.provider_key, best->link.kind, "Provider");
            const std::string line = best == nullptr ? prediction.book_line : best->link.line;
            const std::string price = best == nullptr ? prediction.odds : best->link.price;
            const double expected = HasAmericanOddsText(price) ? ScenarioExpectedProfit(price, probability, scenario_stake_) : 0.0;

            std::ofstream file(ScenarioJournalFile(), std::ios::app);
            if (!file)
            {
                status_ = "Could not write the scenario journal.";
                return;
            }
            file << TodayDateLabel() << '\t'
                 << aegis::NowTimeLabel() << '\t'
                 << TsvField(prediction.game_id) << '\t'
                 << TsvField(prediction.matchup) << '\t'
                 << TsvField(prediction.market) << '\t'
                 << TsvField(prediction.pick) << '\t'
                 << prediction.confidence_value << '\t'
                 << probability << '\t'
                 << std::fixed << std::setprecision(2) << scenario_stake_ << '\t'
                 << TsvField(provider) << '\t'
                 << TsvField(line.empty() ? "--" : line) << '\t'
                 << TsvField(price.empty() ? "--" : price) << '\t'
                 << std::fixed << std::setprecision(2) << expected << '\n';
            file.close();
            PruneLocalTsvFile(ScenarioJournalFile(), 2000);
            status_ = "Scenario read saved to the local decision journal.";
        }

        std::vector<DecisionJournalRow> ReadScenarioJournalRows(int max_rows = 300) const
        {
            std::ifstream file(ScenarioJournalFile());
            std::vector<DecisionJournalRow> rows;
            if (!file)
                return rows;
            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 13)
                    continue;
                DecisionJournalRow row;
                row.date = parts[0];
                row.time = parts[1];
                row.game_id = parts[2];
                row.matchup = parts[3];
                row.market = parts[4];
                row.pick = parts[5];
                row.confidence = std::clamp(std::atoi(parts[6].c_str()), 0, 100);
                row.probability = std::clamp(std::atoi(parts[7].c_str()), 0, 100);
                row.stake = std::max(0.0, std::atof(parts[8].c_str()));
                row.provider = parts[9];
                row.line = parts[10];
                row.price = parts[11];
                row.expected_return = std::atof(parts[12].c_str());
                rows.push_back(row);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            return rows;
        }

        void ClearScenarioJournal()
        {
            std::error_code ec;
            std::filesystem::remove(ScenarioJournalFile(), ec);
            status_ = "Scenario decision journal cleared.";
        }

        bool ProviderNamesMatch(const std::string& saved_provider, const aegis::BetLink& link) const
        {
            const std::string saved = aegis::Lower(aegis::Trim(saved_provider));
            if (saved.empty() || saved.find("no priced") != std::string::npos)
                return false;
            const std::string current = aegis::Lower(FirstNonEmpty(link.title, link.provider_key, link.kind, "Provider"));
            if (current.empty())
                return false;
            return current.find(saved) != std::string::npos || saved.find(current) != std::string::npos;
        }

        JournalLineComparison JournalCurrentComparison(const DecisionJournalRow& row) const
        {
            JournalLineComparison result;
            const aegis::Prediction* prediction = PredictionByGameId(row.game_id);
            const aegis::Game* game = GameById(row.game_id);
            std::vector<ScenarioLine> lines = ScenarioLines(prediction, game);
            const int probability = std::clamp(row.probability > 0 ? row.probability : row.confidence, 1, 99);
            const double stake = std::max(0.0, row.stake);
            int best_any = -1;
            int best_same = -1;
            double best_any_score = -DBL_MAX;
            double best_same_score = -DBL_MAX;
            for (int i = 0; i < static_cast<int>(lines.size()); ++i)
            {
                const aegis::BetLink& link = lines[static_cast<size_t>(i)].link;
                if (!HasAmericanOddsText(link.price))
                    continue;
                const double expected = ScenarioExpectedProfit(link.price, probability, stake);
                if (expected > best_any_score)
                {
                    best_any_score = expected;
                    best_any = i;
                }
                if (ProviderNamesMatch(row.provider, link) && expected > best_same_score)
                {
                    best_same_score = expected;
                    best_same = i;
                }
            }

            const int chosen = best_same >= 0 ? best_same : best_any;
            if (chosen < 0)
                return result;

            const ScenarioLine& current = lines[static_cast<size_t>(chosen)];
            const int probability_for_ev = std::clamp(probability, 1, 99);
            result.found = true;
            result.same_provider = best_same >= 0;
            result.provider = FirstNonEmpty(current.link.title, current.link.provider_key, current.link.kind, "Provider");
            result.line = current.link.line.empty() ? "--" : current.link.line;
            result.price = current.link.price.empty() ? "--" : current.link.price;
            result.scope = result.same_provider ? "Same provider" : "Best current";
            result.current_expected = ScenarioExpectedProfit(result.price, probability_for_ev, stake);
            result.saved_priced = HasAmericanOddsText(row.price);
            if (result.saved_priced)
            {
                const double saved_to_win = aegis::ToWin(row.price, stake);
                const double current_to_win = aegis::ToWin(result.price, stake);
                const double saved_expected = ScenarioExpectedProfit(row.price, probability_for_ev, stake);
                result.clv_delta = saved_to_win - current_to_win;
                result.ev_delta = saved_expected - result.current_expected;
            }
            return result;
        }

        std::string ClvReadLabel(const JournalLineComparison& comparison) const
        {
            if (!comparison.found)
                return "No current line";
            if (!comparison.saved_priced)
                return "No saved price";
            if (std::fabs(comparison.clv_delta) < 0.01)
                return "Flat";
            return comparison.clv_delta > 0.0 ? "Saved better" : "Current better";
        }

        std::vector<aegis::InfoItem> ScenarioClvSummaryRows() const
        {
            const std::vector<DecisionJournalRow> rows = ReadScenarioJournalRows(500);
            int comparable = 0;
            int favorable = 0;
            int current_better = 0;
            double net_clv = 0.0;
            for (const DecisionJournalRow& row : rows)
            {
                const JournalLineComparison comparison = JournalCurrentComparison(row);
                if (!comparison.found || !comparison.saved_priced)
                    continue;
                ++comparable;
                net_clv += comparison.clv_delta;
                if (comparison.clv_delta > 0.01)
                    ++favorable;
                else if (comparison.clv_delta < -0.01)
                    ++current_better;
            }
            return {
                {"Saved reads", "", std::to_string(static_cast<int>(rows.size())), "", "Scenario snapshots saved locally from the lab."},
                {"Comparable now", "", std::to_string(comparable), "", "Saved reads with a current direct-source price to compare."},
                {"Saved better", "", std::to_string(favorable), "", "Saved price pays more than the current comparable line at the same stake."},
                {"Current better", "", std::to_string(current_better), "", "Current line pays more than the saved read."},
                {"Net CLV", "", comparable > 0 ? FormatSignedMoneyText(net_clv) : "--", "", "Aggregate payout edge at saved stake sizes across comparable reads."},
                {"Refresh basis", "", state_.source_badge, "", "CLV comparison rebuilds from the latest native sports board."}
            };
        }

        void RenderScenarioJournalPanel()
        {
            const std::vector<DecisionJournalRow> rows = ReadScenarioJournalRows(160);
            ImGui::Spacing();
            ImGui::BeginChild("scenario_journal", ImVec2(0, 430.0f), true);
            CardHeader("Decision Journal", "Saved reads with current-line value", active_view_);
            if (AegisButton("Export Workspace", ImVec2(164.0f, 36.0f), false))
                ExportWorkspaceReport();
            ImGui::SameLine();
            if (AegisButton("Clear Journal", ImVec2(134.0f, 36.0f), false))
                ClearScenarioJournal();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 36.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::Spacing();
            if (rows.empty())
            {
                EmptyState("No saved scenario reads", "Use Save Read in Scenario Lab to retain the model probability, stake, best line, and EV snapshot.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("scenario_journal_rows", 9, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Time", ImGuiTableColumnFlags_WidthFixed, 70.0f);
                ImGui::TableSetupColumn("Matchup", ImGuiTableColumnFlags_WidthStretch, 1.2f);
                ImGui::TableSetupColumn("Pick", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableSetupColumn("Prob", ImGuiTableColumnFlags_WidthFixed, 64.0f);
                ImGui::TableSetupColumn("Stake", ImGuiTableColumnFlags_WidthFixed, 82.0f);
                ImGui::TableSetupColumn("Saved", ImGuiTableColumnFlags_WidthStretch, 0.8f);
                ImGui::TableSetupColumn("Current", ImGuiTableColumnFlags_WidthStretch, 0.8f);
                ImGui::TableSetupColumn("CLV", ImGuiTableColumnFlags_WidthFixed, 96.0f);
                ImGui::TableSetupColumn("EV", ImGuiTableColumnFlags_WidthFixed, 86.0f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (auto it = rows.rbegin(); it != rows.rend() && rendered < 14; ++it, ++rendered)
                {
                    const JournalLineComparison comparison = JournalCurrentComparison(*it);
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(it->time.c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::TextUnformatted(it->matchup.c_str());
                    ImGui::TableSetColumnIndex(2);
                    TextGreen(it->pick.c_str());
                    TextMuted(it->market.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::Text("%d%%", it->probability);
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(FormatMoneyText(it->stake).c_str());
                    ImGui::TableSetColumnIndex(5);
                    TextGreen(it->price.c_str());
                    TextMuted((it->provider + " / " + it->line).c_str());
                    ImGui::TableSetColumnIndex(6);
                    if (comparison.found)
                    {
                        TextGreen(comparison.price.c_str());
                        TextMuted((comparison.provider + " / " + comparison.scope).c_str());
                    }
                    else
                    {
                        TextMuted("--");
                    }
                    ImGui::TableSetColumnIndex(7);
                    if (!comparison.found || !comparison.saved_priced)
                    {
                        TextMuted(ClvReadLabel(comparison).c_str());
                    }
                    else if (comparison.clv_delta >= 0.0)
                    {
                        TextGreen(FormatSignedMoneyText(comparison.clv_delta).c_str());
                        TextMuted(ClvReadLabel(comparison).c_str());
                    }
                    else
                    {
                        ImGui::TextColored(V4(1.0f, 0.45f, 0.35f, 1.0f), "%s", FormatSignedMoneyText(comparison.clv_delta).c_str());
                        TextMuted(ClvReadLabel(comparison).c_str());
                    }
                    ImGui::TableSetColumnIndex(8);
                    if (it->expected_return >= 0.0)
                        TextGreen(FormatSignedMoneyText(it->expected_return).c_str());
                    else
                        ImGui::TextColored(V4(1.0f, 0.45f, 0.35f, 1.0f), "%s", FormatSignedMoneyText(it->expected_return).c_str());
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderWatchlist(float width)
        {
            ImGui::BeginChild("watchlist", ImVec2(0, 0), true);
            CardHeader("Bet Slip & Order Preview", "Manual controlled workflow", View::Watchlist);
            ImGui::TextWrapped("Pinned markets become slip tickets here. Paper submission is local only. Live mode prepares a manual provider handoff and does not submit orders automatically.");
            ImGui::Spacing();
            ImGui::InputDouble("Preview amount", &preview_amount_, 1.0, 10.0, "%.2f");
            preview_amount_ = std::clamp(preview_amount_, 1.0, std::max(1.0, config_.max_ticket_amount));
            ImGui::SameLine();
            ImGui::Checkbox("Live preview", &live_order_preview_);
            if (config_.paper_only_mode)
                live_order_preview_ = false;
            if (live_order_preview_)
            {
                ImGui::SameLine();
                if (config_.require_live_confirmation)
                    ImGui::Checkbox("I understand live orders require manual provider confirmation", &live_acknowledged_);
                else
                    live_acknowledged_ = true;
                if (!config_.responsible_use_accepted || !config_.legal_location_confirmed)
                    ImGui::TextColored(V4(1.0f, 0.62f, 0.22f, 1.0f), "Manual provider handoff is locked until responsible-use and legal/location acknowledgements are saved in Settings.");
            }
            else
            {
                live_acknowledged_ = false;
            }
            ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "Limit %s / confidence floor %d%% / %s",
                FormatMoneyText(config_.max_ticket_amount).c_str(),
                config_.min_ticket_confidence,
                config_.paper_only_mode ? "paper-only mode" : "manual provider preview");
            ImGui::SameLine();
            TextMuted(("Exposure today " + FormatMoneyText(TodayPreviewExposure()) + " / " + FormatMoneyText(config_.daily_exposure_limit)).c_str());
            if (watchlist_ids_.empty())
            {
                EmptyState("No slip tickets yet", "Open an AI breakdown and add a matchup to the slip/watchlist.");
                ImGui::EndChild();
                return;
            }

            const float card_w = std::max(280.0f, (width - 36.0f) * 0.5f);
            int rendered = 0;
            for (const std::string& id : watchlist_ids_)
            {
                const aegis::Prediction* prediction = PredictionByGameId(id);
                const aegis::Game* game = GameById(id);
                if (prediction == nullptr && game == nullptr)
                    continue;
                if (rendered > 0 && rendered % 2 != 0)
                    ImGui::SameLine();
                RenderWatchCard(game, prediction, ImVec2(card_w, 218.0f));
                ++rendered;
            }
            if (rendered == 0)
                EmptyState("Pinned markets are outside the current slate", "They will reappear when the direct-source board includes those games again.");
            ImGui::EndChild();
        }

        void RenderWatchCard(const aegis::Game* game, const aegis::Prediction* prediction, ImVec2 size)
        {
            const std::string id = game != nullptr ? game->id : prediction->game_id;
            ImGui::PushID(id.c_str());
            ImGui::BeginChild("watch_card", size, true, ImGuiWindowFlags_NoScrollbar);
            const std::string title = prediction != nullptr ? prediction->matchup : game->matchup;
            TextGreen(prediction != nullptr ? prediction->market.c_str() : "Market watch");
            ImGui::PushFont(g_font_bold);
            ImGui::TextWrapped("%s", title.c_str());
            ImGui::PopFont();
            if (prediction != nullptr)
            {
                ImGui::Text("Pick: %s", prediction->pick.c_str());
                ImGui::Text("Confidence: %s", prediction->confidence.c_str());
                ImGui::Text("Preview amount: $%.2f", preview_amount_);
                TextMuted(ActionLabel(*prediction).c_str());
                TextMuted(OrderPreviewLine(*prediction).c_str());
            }
            else if (game != nullptr)
            {
                ImGui::Text("Status: %s", game->status_label.c_str());
                RenderStateBadge(SourceBadgeText(*game));
                ImGui::SameLine();
                TextMuted(game->feed_age_label.c_str());
            }
            char note_buffer[180]{};
            std::snprintf(note_buffer, sizeof(note_buffer), "%s", WatchNote(id).c_str());
            ImGui::SetNextItemWidth(-1);
            if (ImGui::InputTextWithHint("##watch_note", "Reason for watching", note_buffer, sizeof(note_buffer)))
                SetWatchNote(id, note_buffer);
            if (AegisButton("Open Breakdown", ImVec2(140.0f, 30.0f), false))
                OpenGamePrediction(id);
            ImGui::SameLine();
            if (prediction != nullptr && AegisButton("Lab", ImVec2(62.0f, 30.0f), false))
            {
                selected_prediction_ = static_cast<int>(prediction - state_.predictions.data());
                selected_game_id_ = prediction->game_id;
                active_view_ = View::Scenario;
            }
            ImGui::SameLine();
            if (prediction != nullptr && AegisButton(live_order_preview_ ? "Open Provider" : "Paper Submit", ImVec2(128.0f, 30.0f), false))
                SubmitSlipPreview(*prediction);
            ImGui::SameLine();
            if (AegisButton("Remove", ImVec2(96.0f, 30.0f), false))
                RemoveWatch(id);
            ImGui::EndChild();
            ImGui::PopID();
        }

        void RenderSettings(float)
        {
            ImGui::BeginChild("settings", ImVec2(0, 520), true);
            CardHeader("Model Data Settings", "Automatic and configurable inputs", View::Settings);
            ImGui::Text("App version: %s", AppVersionLabel().c_str());
            ImGui::Text("Auth base URL: %s", config_.auth_base_url.c_str());
            ImGui::Text("Sports source: native direct provider feeds");
            ImGui::Text("Odds source: %s", config_.odds_api_key.empty() ? "scoreboard only until key is saved" : "The Odds API key saved");
            ImGui::TextWrapped("Authentication uses the launcher account bridge. Sports data and confidence are rebuilt inside the desktop app from direct provider hosts.");
            ImGui::Spacing();
            StyledInputText("Odds API Key", "##odds_api_key", odds_api_key_, sizeof(odds_api_key_), ImGuiInputTextFlags_Password);
            StyledInputText("Kalshi API Key ID", "##kalshi_key_id", kalshi_key_id_, sizeof(kalshi_key_id_));
            ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "%s", "Kalshi Private Key");
            ImGui::InputTextMultiline("##kalshi_private_key", kalshi_private_key_, sizeof(kalshi_private_key_), ImVec2(-1, 74.0f), ImGuiInputTextFlags_Password);
            StyledInputText("Favorite Teams", "##favorite_teams", favorite_teams_, sizeof(favorite_teams_));
            StyledInputText("Favorite Leagues", "##favorite_leagues", favorite_leagues_, sizeof(favorite_leagues_));
            ImGui::SeparatorText("External Data Adapters");
            StyledInputText("Injury Feed URL", "##injury_feed_url", injury_feed_url_, sizeof(injury_feed_url_));
            StyledInputText("Lineup Feed URL", "##lineup_feed_url", lineup_feed_url_, sizeof(lineup_feed_url_));
            StyledInputText("News Feed URL", "##news_feed_url", news_feed_url_, sizeof(news_feed_url_));
            StyledInputText("Player Props Feed URL", "##props_feed_url", props_feed_url_, sizeof(props_feed_url_));
            ImGui::PushItemWidth(170.0f);
            ImGui::InputInt("Refresh seconds", &settings_refresh_seconds_);
            ImGui::SameLine();
            ImGui::InputInt("Tracked games", &settings_tracked_games_);
            ImGui::SameLine();
            ImGui::InputInt("Model rows", &settings_model_count_);
            ImGui::PopItemWidth();
            ImGui::SeparatorText("Ticket Controls");
            ImGui::PushItemWidth(170.0f);
            ImGui::InputDouble("Max ticket amount", &settings_max_ticket_amount_, 5.0, 25.0, "%.2f");
            ImGui::SameLine();
            ImGui::InputDouble("Daily exposure limit", &settings_daily_exposure_limit_, 25.0, 100.0, "%.2f");
            ImGui::SameLine();
            ImGui::InputInt("Min confidence", &settings_min_ticket_confidence_);
            ImGui::PopItemWidth();
            ImGui::Checkbox("Paper-only mode", &settings_paper_only_mode_);
            ImGui::SameLine();
            ImGui::Checkbox("Require live confirmation", &settings_require_live_confirmation_);
            ImGui::SameLine();
            ImGui::Checkbox("In-app notifications", &settings_notifications_enabled_);
            ImGui::Checkbox("Player props workspace", &settings_player_props_enabled_);
            ImGui::SameLine();
            ImGui::Checkbox("Optional bankroll analytics", &settings_bankroll_analytics_enabled_);
            ImGui::SameLine();
            ImGui::Checkbox("Responsible-use acknowledgement", &settings_responsible_use_accepted_);
            ImGui::SameLine();
            ImGui::Checkbox("Legal/location reminder acknowledged", &settings_legal_location_confirmed_);
            ImGui::PushItemWidth(170.0f);
            ImGui::InputDouble("Starting bankroll", &settings_bankroll_starting_amount_, 25.0, 100.0, "%.2f");
            ImGui::PopItemWidth();
            ImGui::SeparatorText("Alert Rules");
            ImGui::PushItemWidth(170.0f);
            ImGui::InputInt("Alert confidence", &settings_alert_confidence_threshold_);
            ImGui::PopItemWidth();
            ImGui::SameLine();
            ImGui::Checkbox("Watchlist alerts only", &settings_alert_watchlist_only_);
            ImGui::SameLine();
            ImGui::Checkbox("Line movement only", &settings_alert_line_movement_only_);
            ImGui::Spacing();
            if (AegisButton("Save Sources", ImVec2(132, 38), true))
                SaveSourceSettings();
            ImGui::SameLine();
            if (AegisButton("Validate Key", ImVec2(132, 38), false))
                ValidateOddsKey();
            ImGui::SameLine();
            if (AegisButton("Validate Feeds", ImVec2(142, 38), false))
                ValidateDataAdapters();
            ImGui::SameLine();
            if (AegisButton("Refresh Now", ImVec2(132, 38), false))
                BeginSportsRefresh(false, "Manual refresh started", "Updating the live sports board from direct provider feeds.");
            ImGui::SameLine();
            if (AegisButton("Get Odds Key", ImVec2(132, 38), false))
                aegis::OpenExternalUrl("https://the-odds-api.com/");
            ImGui::SameLine();
            if (AegisButton("Clear Key", ImVec2(112, 38), false))
                ClearOddsKey();
            ImGui::Spacing();
            if (AegisButton("Save Kalshi", ImVec2(132, 38), false))
                SaveKalshiSettings();
            ImGui::SameLine();
            if (AegisButton("Validate Kalshi", ImVec2(144, 38), false))
                ValidateKalshiFormat();
            ImGui::SameLine();
            if (AegisButton("Clear Kalshi", ImVec2(132, 38), false))
                ClearKalshiCredentials();
            ImGui::Spacing();
            ImGui::SeparatorText("Local Data Maintenance");
            if (AegisButton("Clear Alerts", ImVec2(132, 38), false))
                ClearNotificationJournal();
            ImGui::SameLine();
            if (AegisButton("Reset Exposure", ImVec2(142, 38), false))
                ClearExposureLedger();
            ImGui::SameLine();
            if (AegisButton("Clear Watchlist", ImVec2(150, 38), false))
                ClearWatchlist();
            ImGui::SameLine();
            if (AegisButton("Clear Journal", ImVec2(136, 38), false))
                ClearScenarioJournal();
            ImGui::SameLine();
            if (AegisButton("Clear Audit", ImVec2(126, 38), false))
                ClearPredictionAudit();
            ImGui::SameLine();
            if (AegisButton("Open Compliance", ImVec2(162, 38), false))
                aegis::OpenExternalUrl(ComplianceChecklistPath().string());
            ImGui::Spacing();
            if (AegisButton("Sign Out", ImVec2(132, 38), false))
            {
                ++refresh_request_id_;
                initial_sync_ = false;
                authenticated_ = false;
                cookie_header_.clear();
                aegis::DeleteRememberedCredentials();
                status_ = "Signed out and cleared remembered credentials.";
            }
            ImGui::EndChild();
            RenderInfoGridCard("Odds API Health", OddsApiHealthRows(), 0, 250.0f);
            RenderInfoGridCard("Kalshi Access", KalshiHealthRows(), 0, 250.0f);
            RenderInfoGridCard("Model Sources", state_.model_sources, 0, 390.0f);
            RenderInfoGridCard("Confidence Inputs", ConfidenceInputRows(), 0, 250.0f);
            RenderInfoGridCard("Settings Validation", SettingsValidationRows(), 0, 250.0f);
            RenderInfoGridCard("Responsible Mode", ResponsibleModeRows(), 0, 190.0f);
            RenderInfoGridCard("Compliance & Terms", ComplianceChecklistRows(), 0, 300.0f);
            RenderInfoGridCard("Setup Status", SetupStatusRows(), 0, 190.0f);
            RenderInfoGridCard("Startup Integrity", startup_integrity_, 0, 240.0f);
            RenderInfoGridCard("Setup Checklist", SetupChecklistRows(), 0, 250.0f);
            RenderInfoGridCard("Adapter Schema Contracts", aegis::OptionalFeedSchemaRows(), 0, 330.0f);
            RenderDiagnosticsPanel();
        }

        std::vector<aegis::InfoItem> ConfidenceInputRows() const
        {
            std::vector<aegis::InfoItem> rows = {
                {"Active now", "", "Scoreboard, status, records, public history", "", "These are used automatically when the public feed supplies them."},
                {"Configurable now", "", "Odds API key, refresh cadence, model rows", "", "Settings save these locally and the next refresh rebuilds sportsbook links and edges."},
                {"Tracked locally", "", "Line movement snapshots", "", "Matched sportsbook prices are cached on this machine for movement labels."},
                {"Needs vendor feed", "", "Injuries, lineups, deep tracking", "", "Confidence stays capped until verified availability and tracking feeds are connected."}
            };
            for (const aegis::InfoItem& source : state_.model_sources)
            {
                const std::string contract = aegis::Lower(source.source);
                if (contract.find("aegis.injuries") == std::string::npos &&
                    contract.find("aegis.lineups") == std::string::npos &&
                    contract.find("aegis.news") == std::string::npos &&
                    contract.find("aegis.props") == std::string::npos)
                {
                    continue;
                }
                rows.push_back({
                    FirstNonEmpty(source.name, source.label, "Optional feed"),
                    "",
                    FirstNonEmpty(source.value, source.status, "Validated"),
                    source.source,
                    source.detail
                });
            }
            return rows;
        }

        std::string SetupReadyCount() const
        {
            return SetupComplete() ? "Complete" : std::to_string(SetupReadyValue()) + "/" + std::to_string(SetupReadyTotal());
        }

        int SetupReadyTotal() const
        {
            return 7;
        }

        int SetupReadyValue() const
        {
            int ready = 0;
            ready += authenticated_ ? 1 : 0;
            ready += !aegis::Trim(config_.odds_api_key).empty() ? 1 : 0;
            ready += !state_.games.empty() && !IsBoardStale() ? 1 : 0;
            ready += CountOddsIssueGames() == 0 && CountOddsMatchedGames() > 0 ? 1 : 0;
            ready += config_.paper_only_mode || config_.require_live_confirmation ? 1 : 0;
            ready += config_.responsible_use_accepted ? 1 : 0;
            ready += config_.legal_location_confirmed ? 1 : 0;
            return ready;
        }

        bool SetupComplete() const
        {
            return SetupReadyValue() >= SetupReadyTotal();
        }

        std::string SetupStateLabel() const
        {
            if (SetupComplete())
                return "Setup Complete";
            if (!authenticated_)
                return "Needs auth";
            if (aegis::Trim(config_.odds_api_key).empty())
                return "Needs Odds API key";
            if (IsBoardStale() || state_.games.empty())
                return "Needs refresh";
            if (CountOddsMatchedGames() == 0 || CountOddsIssueGames() > 0)
                return "Needs odds review";
            if (!config_.responsible_use_accepted || !config_.legal_location_confirmed)
                return "Needs safety acknowledgement";
            return "Needs review";
        }

        std::vector<aegis::InfoItem> SetupStatusRows() const
        {
            const int ready = SetupReadyValue();
            return {
                {"Setup state", "", SetupStateLabel(), std::to_string(ready) + "/" + std::to_string(SetupReadyTotal()), SetupComplete() ? "Minimum safe requirements are met for direct-source research mode." : "Complete the remaining checklist items before treating the workspace as production-ready.", "", "", "", "", "", "", "Setup", SetupComplete() ? "Complete" : "In progress"},
                {"Direct board", "", (!state_.games.empty() && !IsBoardStale()) ? "Ready" : "Needs refresh", "", state_.games.empty() ? "No direct-source board is loaded yet." : "Last refresh " + AgeLabel(SecondsSinceLastRefresh()) + " with " + std::to_string(static_cast<int>(state_.games.size())) + " events."},
                {"Odds matching", "", std::to_string(CountOddsMatchedGames()) + " matched", "", std::to_string(CountOddsIssueGames()) + " games still need provider review before they should be trusted as matched sportsbook markets."},
                {"Safety gate", "", (config_.paper_only_mode || config_.require_live_confirmation) ? "Guarded" : "Needs guard", "", "Paper-only mode or live-confirmation mode must stay enabled before setup is considered complete."}
            };
        }

        std::filesystem::path ConfigFilePath() const
        {
            const std::filesystem::path packaged = aegis::ExecutableDirectory() / "AegisSportsBettingAI.config.ini";
            if (std::filesystem::exists(packaged))
                return packaged;
            return std::filesystem::current_path() / "AegisSportsBettingAI.config.ini";
        }

        std::vector<aegis::InfoItem> BuildStartupIntegrityRows() const
        {
            std::vector<aegis::InfoItem> rows;
            const std::filesystem::path config_path = ConfigFilePath();
            const std::filesystem::path appdata = aegis::AppDataDirectory();
            const std::filesystem::path exe_path = aegis::ExecutableDirectory() / "AegisSportsBettingAI.exe";
            const bool config_exists = std::filesystem::exists(config_path);
            const bool exe_exists = std::filesystem::exists(exe_path);
            const bool compliance_exists = std::filesystem::exists(ComplianceChecklistPath());

            bool appdata_write_ok = false;
            const std::filesystem::path final_probe = appdata / "startup-integrity.check";
            const std::filesystem::path temp_probe = TempOutputPath(final_probe);
            {
                std::ofstream probe(temp_probe, std::ios::trunc);
                if (probe)
                    probe << "ok\n";
                probe.close();
                appdata_write_ok = probe && CommitTempFile(temp_probe, final_probe);
            }
            std::error_code ec;
            std::filesystem::remove(final_probe, ec);

            rows.push_back({"Config file", "", config_exists ? "Found" : "Missing", "", config_exists ? config_path.string() : "The app will fall back to built-in safe defaults until a config file is saved."});
            rows.push_back({"Config schema", "", "v" + std::to_string(config_.config_schema_version), "", config_.migrated_config ? "Legacy config was migrated on startup." : "Current schema loaded cleanly."});
            rows.push_back({"AppData write", "", appdata_write_ok ? "Writable" : "Blocked", "", appdata_write_ok ? appdata.string() : "Local journals, encrypted credentials, and reports may fail until this folder is writable."});
            rows.push_back({"Release executable", "", exe_exists ? "Present" : "Dev mode", "", exe_exists ? exe_path.string() : "Running outside the packaged release folder or before build output exists."});
            rows.push_back({"Compliance doc", "", compliance_exists ? "Available" : "Missing", "", ComplianceChecklistPath().string()});
            rows.push_back({"Secret hygiene", "", (config_.odds_api_key.empty() && config_.kalshi_private_key.empty()) ? "No plaintext config secrets" : "Encrypted runtime value loaded", "", "Provider secrets are loaded from DPAPI when present and are not written back to the INI file."});
            return rows;
        }

        std::string ConfiguredLabel(const std::string& value) const
        {
            return aegis::Trim(value).empty() ? "Not configured" : "Configured";
        }

        bool IsHttpUrl(const std::string& value) const
        {
            const std::string url = aegis::Lower(aegis::Trim(value));
            return url.rfind("https://", 0) == 0 || url.rfind("http://", 0) == 0;
        }

        static bool IsHttpUrlValue(const std::string& value)
        {
            const std::string url = aegis::Lower(aegis::Trim(value));
            return url.rfind("https://", 0) == 0 || url.rfind("http://", 0) == 0;
        }

        static aegis::OptionalFeedValidationResult ValidateOptionalFeedUrl(const std::string& key, const std::string& name, const std::string& url)
        {
            aegis::OptionalFeedValidationResult result;
            result.feed_key = key;
            result.title = name;
            result.contract = aegis::OptionalFeedContractLabel(key);
            const std::string trimmed = aegis::Trim(url);
            if (trimmed.empty())
            {
                result.status = "Not configured";
                result.detail = name + " URL is empty.";
                return result;
            }
            if (!IsHttpUrlValue(trimmed))
            {
                result.status = "Invalid URL";
                result.detail = name + " must start with http:// or https://.";
                result.errors = 1;
                return result;
            }

            const aegis::HttpResponse response = aegis::HttpGet(trimmed);
            result.status_code = response.status_code;
            if (!response.error.empty())
            {
                result.status = "Network error";
                result.detail = response.error;
                result.errors = 1;
                return result;
            }
            if (response.status_code < 200 || response.status_code >= 300)
            {
                result.status = "HTTP " + std::to_string(response.status_code);
                result.detail = name + " responded but was not a success status: " + trimmed;
                result.errors = 1;
                return result;
            }

            result = aegis::ValidateOptionalFeedBody(key, response.body);
            result.title = name;
            result.reachable = true;
            result.status_code = response.status_code;
            result.detail = "HTTP " + std::to_string(response.status_code) + " / " + result.contract + " / " + result.detail;
            return result;
        }

        static std::vector<aegis::OptionalFeedValidationResult> CollectConfiguredOptionalFeeds(const aegis::Config& config, std::map<std::string, int>* latency_ms = nullptr)
        {
            struct Target
            {
                std::string key;
                std::string name;
                std::string url;
            };

            const std::vector<Target> targets = {
                {"injury", "Injury feed", config.injury_feed_url},
                {"lineup", "Lineup feed", config.lineup_feed_url},
                {"news", "News feed", config.news_feed_url},
                {"props", "Player props feed", config.props_feed_url}
            };

            std::vector<aegis::OptionalFeedValidationResult> results;
            for (const Target& target : targets)
            {
                if (aegis::Trim(target.url).empty())
                    continue;
                const auto started = std::chrono::steady_clock::now();
                results.push_back(ValidateOptionalFeedUrl(target.key, target.name, target.url));
                if (latency_ms != nullptr)
                {
                    (*latency_ms)[target.key] = static_cast<int>(std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::steady_clock::now() - started).count());
                }
            }
            return results;
        }

        std::string AdapterStatus(const std::string& key, const std::string& url) const
        {
            if (aegis::Trim(url).empty())
                return "Not configured";
            const auto it = adapter_probes_.find(key);
            if (it != adapter_probes_.end() && it->second.checked)
                return it->second.status;
            return "Configured";
        }

        std::string AdapterDetail(const std::string& key, const std::string& url, const std::string& empty_detail) const
        {
            const std::string trimmed = aegis::Trim(url);
            if (trimmed.empty())
                return empty_detail;
            const auto it = adapter_probes_.find(key);
            if (it != adapter_probes_.end() && it->second.checked)
            {
                if (it->second.detail.empty())
                    return trimmed;
                if (it->second.records > 0 || it->second.errors > 0 || it->second.warnings > 0)
                {
                    return it->second.detail + " Records " + std::to_string(it->second.records) +
                        " / errors " + std::to_string(it->second.errors) +
                        " / warnings " + std::to_string(it->second.warnings) + ".";
                }
                return it->second.detail;
            }
            if (!IsHttpUrl(trimmed))
                return "Configured value is not an HTTP or HTTPS URL: " + trimmed;
            return "Configured but not validated this session: " + trimmed;
        }

        std::vector<aegis::InfoItem> DataAdapterRows() const
        {
            const std::string injury = aegis::Trim(std::string(injury_feed_url_));
            const std::string lineup = aegis::Trim(std::string(lineup_feed_url_));
            const std::string news = aegis::Trim(std::string(news_feed_url_));
            const std::string props = aegis::Trim(std::string(props_feed_url_));
            return {
                {"Injury feed", "", AdapterStatus("injury", injury), aegis::OptionalFeedContractLabel("injury"), AdapterDetail("injury", injury, "Add a verified injury provider URL to reduce availability uncertainty.")},
                {"Lineup feed", "", AdapterStatus("lineup", lineup), aegis::OptionalFeedContractLabel("lineup"), AdapterDetail("lineup", lineup, "Starting lineups and scratches remain manual checks until configured.")},
                {"News feed", "", AdapterStatus("news", news), aegis::OptionalFeedContractLabel("news"), AdapterDetail("news", news, "Breaking team news is not yet connected to confidence scoring.")},
                {"Props feed", "", AdapterStatus("props", props), aegis::OptionalFeedContractLabel("props"), AdapterDetail("props", props, "Player prop rows stay hidden until a verified provider is configured.")},
                {"Odds feed", "", config_.odds_api_key.empty() ? "Needs key" : "Saved", "", "The Odds API powers sportsbook comparison when a valid key is saved."},
                {"Adapter policy", "", "Explicit", "", "Missing vendor feeds are shown as gaps and do not become fake live data."}
            };
        }

        std::vector<aegis::InfoItem> ResponsibleUseRows() const
        {
            return {
                {"Execution", "", "Manual only", "", "Aegis stages paper reads and provider handoffs; it does not auto-submit wagers or exchange orders."},
                {"Paper mode", "", config_.paper_only_mode ? "On" : "Off", "", config_.paper_only_mode ? "Live provider handoff is disabled." : "Manual provider handoff still requires confirmation."},
                {"Age/location", "", config_.legal_location_confirmed ? "Acknowledged" : "Needs acknowledgement", "", "Users must confirm eligibility and local rules outside the app before any real-money action."},
                {"Use acknowledgement", "", config_.responsible_use_accepted ? "Accepted" : "Needs acknowledgement", "", "The app presents probabilities as uncertain estimates, not guarantees."},
                {"Exposure guard", "", FormatMoneyText(config_.daily_exposure_limit), "", "Daily local preview limit blocks staged tickets beyond this amount."},
                {"Confidence floor", "", std::to_string(config_.min_ticket_confidence) + "%", "", "Preview staging is blocked below this model confidence floor."}
            };
        }

        bool OptionalUrlIsValid(const std::string& value) const
        {
            const std::string trimmed = aegis::Trim(value);
            return trimmed.empty() || IsHttpUrl(trimmed);
        }

        std::vector<aegis::InfoItem> SettingsValidationRows() const
        {
            const std::vector<std::pair<std::string, std::string>> urls = {
                {"Injury URL", injury_feed_url_},
                {"Lineup URL", lineup_feed_url_},
                {"News URL", news_feed_url_},
                {"Props URL", props_feed_url_}
            };
            int invalid_urls = 0;
            for (const auto& row : urls)
            {
                if (!OptionalUrlIsValid(row.second))
                    ++invalid_urls;
            }

            const bool risky_safety = !settings_paper_only_mode_ && !settings_require_live_confirmation_;
            const bool exposure_too_low = settings_daily_exposure_limit_ < settings_max_ticket_amount_;
            const bool numeric_ok =
                settings_refresh_seconds_ >= 5 &&
                settings_tracked_games_ >= 12 &&
                settings_model_count_ >= 2 &&
                settings_max_ticket_amount_ >= 1.0 &&
                settings_daily_exposure_limit_ >= 1.0 &&
                settings_min_ticket_confidence_ >= 1 &&
                settings_alert_confidence_threshold_ >= 50;

            return {
                {"Optional URLs", "", invalid_urls == 0 ? "Valid" : std::to_string(invalid_urls) + " invalid", "", invalid_urls == 0 ? "Empty URLs are allowed; configured feed URLs must start with http:// or https://." : "Invalid adapter URLs block Save Sources until corrected."},
                {"Numeric ranges", "", numeric_ok ? "Ready" : "Will clamp", "", "Refresh, tracked games, model rows, exposure, ticket, confidence, and alert ranges are bounded before save."},
                {"Exposure vs ticket", "", exposure_too_low ? "Will raise daily limit" : "Ready", "", exposure_too_low ? "Daily exposure must be at least the maximum ticket amount." : "Daily exposure is at or above the max ticket amount."},
                {"Safety mode", "", risky_safety ? "Will force confirmation" : "Guarded", "", risky_safety ? "Save Sources will keep live confirmation enabled when paper-only mode is off." : "Paper-only mode or live confirmation is enabled."},
                {"Secret storage", "", "DPAPI", "", "Odds and Kalshi secrets are saved to encrypted user storage, never plaintext config."}
            };
        }

        std::vector<aegis::InfoItem> ReleaseReadinessRows() const
        {
            const std::filesystem::path release = aegis::ExecutableDirectory();
            return {
                {"Version", "", AppVersionLabel(), "", "Single version source is src/AppVersion.h and release scripts read it for package metadata."},
                {"Release exe", "", FileSizeLabel(release / "AegisSportsBettingAI.exe"), "", (release / "AegisSportsBettingAI.exe").string()},
                {"Config", "", FileSizeLabel(release / "AegisSportsBettingAI.config.ini"), "", "Non-secret config ships beside the executable; secrets are stored through DPAPI."},
                {"Config schema", "", "v" + std::to_string(config_.config_schema_version), "", config_.migrated_config ? "Legacy config was upgraded and rewritten with current safe defaults." : "Config schema is current."},
                {"Smoke tests", "", std::filesystem::exists(aegis::ExecutableDirectory().parent_path().parent_path() / "tools" / "run_smoke_tests.ps1") ? "Available" : "Needs script", "", "Run the local smoke test script before packaging."},
                {"Packaging", "", std::filesystem::exists(aegis::ExecutableDirectory().parent_path().parent_path() / "tools" / "package_release.ps1") ? "Available" : "Needs script", "", "Packaging excludes screenshots and debug symbols from user-facing builds."},
                {"Docs", "", "Updated", "", "README describes native direct-source sports data and setup requirements."},
                {"Tests", "", "Local harness", "", "Automated smoke tests cover build output, config hygiene, local data, and source markers."}
            };
        }

        std::filesystem::path ComplianceChecklistPath() const
        {
            const std::filesystem::path packaged = aegis::ExecutableDirectory() / "docs" / "COMPLIANCE_CHECKLIST.md";
            if (std::filesystem::exists(packaged))
                return packaged;
            const std::filesystem::path dev = aegis::ExecutableDirectory().parent_path().parent_path() / "docs" / "COMPLIANCE_CHECKLIST.md";
            if (std::filesystem::exists(dev))
                return dev;
            return std::filesystem::current_path() / "docs" / "COMPLIANCE_CHECKLIST.md";
        }

        std::vector<aegis::InfoItem> ComplianceChecklistRows() const
        {
            return {
                {"Manual only", "", "Required", "", "No unattended betting, auto-submit, background order placement, or automatic Kalshi order execution."},
                {"Eligibility", "", config_.legal_location_confirmed ? "Acknowledged" : "Needs review", "", "Users must confirm age, legal location, and local rules outside Aegis before any real-money provider handoff."},
                {"Responsible use", "", config_.responsible_use_accepted ? "Accepted" : "Needs acknowledgement", "", "Confidence, edge, and EV are uncertain estimates, not guarantees or financial advice."},
                {"Disney/ESPN", "", "Review required", "", "Public scoreboard usage must comply with the latest Disney/ESPN terms before distribution."},
                {"The Odds API", "", config_.odds_api_key.empty() ? "Needs key + terms" : "Key saved / terms review", "", "Confirm plan, redistribution limits, quota behavior, responsible-gambling wording, and verification requirements."},
                {"Kalshi", "", "Manual provider only", "", "Review the Developer Agreement, market data terms, event-contract rules, and API key handling before any expanded workflow."},
                {"Optional feeds", "", "Provider license", "", "Every injury, lineup, news, and props vendor must allow display, retention, screenshots, exports, and derived signals."},
                {"Checklist doc", "", std::filesystem::exists(ComplianceChecklistPath()) ? "Available" : "Missing", "", ComplianceChecklistPath().string()}
            };
        }

        std::vector<aegis::InfoItem> PlayerPropRows() const
        {
            return {
                {"Workspace", "", config_.player_props_enabled ? "Enabled" : "Disabled", "", "The prop workspace stays optional and does not affect the main board."},
                {"Props feed", "", ConfiguredLabel(config_.props_feed_url), "", aegis::Trim(config_.props_feed_url).empty() ? "Configure a verified provider before showing player markets." : config_.props_feed_url},
                {"Odds API", "", config_.odds_api_key.empty() ? "Needs key" : "Saved", "", "Sportsbook key can support provider lookups when the account has prop-market access."},
                {"Lineups", "", ConfiguredLabel(config_.lineup_feed_url), "", "Player props should be reviewed with current starting lineup and scratch data."},
                {"Injuries", "", ConfiguredLabel(config_.injury_feed_url), "", "Aegis keeps prop confidence conservative until injury feeds are connected."},
                {"Mode", "", "Research only", "", "Player prop rows are informational and never auto-place a bet."}
            };
        }

        std::vector<aegis::InfoItem> PropRiskRows() const
        {
            return {
                {"Availability", "", "Required", "", "Do not treat a player prop as actionable without current injury and lineup verification."},
                {"Market depth", "", "Provider-gated", "", "Different books expose different prop markets; missing rows are not inferred."},
                {"Limits", "", "User controlled", "", "Prop review uses the same ticket and daily exposure limits as the main slip preview."},
                {"CLV", "", "Journal-ready", "", "Saved prop reads can use the same scenario journal once provider rows are available."},
                {"Export", "", "Included", "", "Prop adapter status is included in the CSV, PDF, and workspace reports."},
                {"Safety", "", "Manual only", "", "Props remain manual review, paper logging, and provider handoff only."}
            };
        }

        std::vector<aegis::InfoItem> OddsApiHealthRows() const
        {
            const bool saved = !aegis::Trim(config_.odds_api_key).empty();
            const std::string validation = odds_validation_.status.empty()
                ? (saved ? "Saved" : "Not saved")
                : odds_validation_.status;
            const std::string detail = odds_validation_.detail.empty()
                ? (saved ? "The key is encrypted with Windows DPAPI. Press Validate Key to test it." : "Paste a key from The Odds API, then press Save Sources.")
                : odds_validation_.detail;
            return {
                {"Storage", "", saved ? "Encrypted" : "Empty", "", saved ? "Saved under LocalAppData with Windows user protection, not plain config text." : "No Odds API key is saved yet."},
                {"Validation", "", validation, "", detail},
                {"Sports list", "", odds_validation_.sports > 0 ? std::to_string(odds_validation_.sports) : "--", "", "Number of sports returned by the key validation call."},
                {"Quota", "", odds_validation_.requests_remaining.empty() ? "--" : odds_validation_.requests_remaining + " left", "", odds_validation_.requests_used.empty() ? "Quota appears after a successful validation response." : odds_validation_.requests_used + " used / last call " + (odds_validation_.requests_last.empty() ? "--" : odds_validation_.requests_last)}
            };
        }

        int CountBookLinesInState(const aegis::SportsState& state) const
        {
            int count = 0;
            for (const aegis::Game& game : state.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    if (link.available && link.kind == "Sportsbook")
                        ++count;
                }
            }
            return count;
        }

        int CountExchangeLinksInState(const aegis::SportsState& state) const
        {
            int count = 0;
            for (const aegis::Game& game : state.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    const std::string key = aegis::Lower(link.kind + " " + link.title + " " + link.provider_key);
                    if (key.find("kalshi") != std::string::npos || key.find("exchange") != std::string::npos)
                        ++count;
                }
            }
            return count;
        }

        ProviderRefreshRecord DefaultProviderRefreshRecord(const std::string& key, const std::string& name, const std::string& detail) const
        {
            ProviderRefreshRecord record;
            record.key = key;
            record.name = name;
            record.detail = detail;
            return record;
        }

        void RecordProviderRefresh(const std::string& key, const std::string& name, const std::string& status, const std::string& detail, bool success, bool failure, const std::string& label, int elapsed_ms)
        {
            ProviderRefreshRecord& record = provider_refresh_records_[key];
            if (record.key.empty())
                record.key = key;
            if (record.name.empty())
                record.name = name;
            record.status = status;
            record.detail = detail;
            record.latency = elapsed_ms > 0 ? std::to_string(elapsed_ms) + " ms" : "--";
            const std::string seen_at = label.empty() ? aegis::NowTimeLabel() : label;
            if (success)
                record.last_success = seen_at;
            if (failure)
                record.last_failure = seen_at;
        }

        void RecordConfiguredFeedRefresh(const std::vector<aegis::OptionalFeedValidationResult>& feeds, const std::string& key, const std::string& name, const std::string& url, const std::string& label, int elapsed_ms)
        {
            const std::string trimmed = aegis::Trim(url);
            if (trimmed.empty())
            {
                RecordProviderRefresh(key, name, "Not configured", "No optional feed URL is saved for this source.", false, false, label, elapsed_ms);
                return;
            }

            const auto it = std::find_if(feeds.begin(), feeds.end(), [&](const aegis::OptionalFeedValidationResult& feed) {
                return feed.feed_key == key;
            });
            if (it == feeds.end())
            {
                RecordProviderRefresh(key, name, "No result", "A URL is configured, but this refresh did not return a validation row.", false, true, label, elapsed_ms);
                return;
            }

            const std::string status = it->status.empty() ? (it->ok ? "Schema valid" : "Schema invalid") : it->status;
            const std::string detail = it->detail.empty()
                ? std::to_string(it->records) + " records / " + std::to_string(it->errors) + " errors / " + std::to_string(it->warnings) + " warnings."
                : it->detail + " Records " + std::to_string(it->records) + " / errors " + std::to_string(it->errors) + " / warnings " + std::to_string(it->warnings) + ".";
            RecordProviderRefresh(key, name, status, detail, it->ok, !it->ok, label, elapsed_ms);
        }

        void UpdateProviderRefreshRecordsFromResult(const RefreshResult& result)
        {
            const std::string label = result.refresh_label.empty() ? aegis::NowTimeLabel() : result.refresh_label;
            auto latency_for = [&](const std::string& key, int fallback_ms) {
                const auto it = result.provider_latency_ms.find(key);
                return it == result.provider_latency_ms.end() ? fallback_ms : it->second;
            };
            if (!result.ok)
            {
                RecordProviderRefresh("scoreboard", "Scoreboard", "Failed", result.status.empty() ? "The native sports refresh did not complete." : result.status, false, true, label, result.elapsed_ms);
                return;
            }

            const bool fallback = result.state.source_badge.find("Fallback") != std::string::npos ||
                (!result.state.games.empty() && result.state.games.front().feed_age_label.find("Fallback") != std::string::npos);
            const int games = static_cast<int>(result.state.games.size());
            const bool scoreboard_ok = games > 0 && !fallback;
            RecordProviderRefresh(
                "scoreboard",
                "Scoreboard",
                scoreboard_ok ? "Success" : (fallback ? "Fallback" : "No events"),
                scoreboard_ok ? std::to_string(games) + " direct scoreboard events loaded." : FirstNonEmpty(result.state.source_label, "No direct scoreboard events were available.", ""),
                scoreboard_ok,
                !scoreboard_ok,
                label,
                latency_for("scoreboard", result.elapsed_ms));

            const int book_lines = CountBookLinesInState(result.state);
            const bool odds_configured = !aegis::Trim(config_.odds_api_key).empty();
            if (!odds_configured)
            {
                RecordProviderRefresh("odds_api", "Odds API", "Not configured", "No Odds API key is saved; sportsbook comparison stays scoreboard-only.", false, false, label, latency_for("odds_api", 0));
            }
            else
            {
                RecordProviderRefresh(
                    "odds_api",
                    "Odds API",
                    book_lines > 0 ? "Success" : "No matched lines",
                    book_lines > 0 ? std::to_string(book_lines) + " matched sportsbook lines were loaded." : "The key is saved, but this refresh did not produce matched sportsbook lines.",
                    book_lines > 0,
                    book_lines == 0,
                    label,
                    latency_for("odds_api", result.elapsed_ms));
            }

            const int exchange_links = CountExchangeLinksInState(result.state);
            RecordProviderRefresh(
                "kalshi",
                "Kalshi",
                exchange_links > 0 ? "Linked" : "Manual only",
                exchange_links > 0 ? std::to_string(exchange_links) + " exchange/provider links are available for manual review." : "Kalshi remains manual provider handoff only; no refresh-time order execution is attempted.",
                exchange_links > 0,
                false,
                label,
                latency_for("kalshi", 0));

            RecordConfiguredFeedRefresh(result.optional_feeds, "injury", "Injury feed", config_.injury_feed_url, label, latency_for("injury", 0));
            RecordConfiguredFeedRefresh(result.optional_feeds, "lineup", "Lineup feed", config_.lineup_feed_url, label, latency_for("lineup", 0));
            RecordConfiguredFeedRefresh(result.optional_feeds, "news", "News feed", config_.news_feed_url, label, latency_for("news", 0));
            RecordConfiguredFeedRefresh(result.optional_feeds, "props", "Player props feed", config_.props_feed_url, label, latency_for("props", 0));
        }

        std::vector<aegis::InfoItem> ProviderRefreshRows() const
        {
            const std::vector<ProviderRefreshRecord> defaults = {
                DefaultProviderRefreshRecord("scoreboard", "Scoreboard", "Direct sports scoreboard source has not refreshed in this session."),
                DefaultProviderRefreshRecord("odds_api", "Odds API", "Save an Odds API key to track sportsbook refresh success and failure."),
                DefaultProviderRefreshRecord("kalshi", "Kalshi", "Manual exchange handoff is tracked separately from sportsbook lines."),
                DefaultProviderRefreshRecord("injury", "Injury feed", "Optional source; configure a URL to validate on refresh."),
                DefaultProviderRefreshRecord("lineup", "Lineup feed", "Optional source; configure a URL to validate on refresh."),
                DefaultProviderRefreshRecord("news", "News feed", "Optional source; configure a URL to validate on refresh."),
                DefaultProviderRefreshRecord("props", "Player props feed", "Optional source; configure a URL to validate on refresh.")
            };

            std::vector<aegis::InfoItem> rows;
            for (ProviderRefreshRecord record : defaults)
            {
                const auto it = provider_refresh_records_.find(record.key);
                if (it != provider_refresh_records_.end())
                    record = it->second;
                aegis::InfoItem row;
                row.name = record.name;
                row.value = record.status;
                row.weight = record.latency;
                row.detail = "Last ok " + record.last_success + " / last fail " + record.last_failure + ". " + record.detail;
                row.status = record.status;
                row.latency = record.latency;
                rows.push_back(row);
            }
            return rows;
        }

        std::vector<aegis::InfoItem> BuildRefreshChangeSummary(const aegis::SportsState& before, const aegis::SportsState& after) const
        {
            if (before.games.empty() && before.predictions.empty())
            {
                return {
                    {"Baseline", "", std::to_string(static_cast<int>(after.games.size())) + " events", "", "First refresh snapshot captured for change tracking."},
                    {"Predictions", "", std::to_string(static_cast<int>(after.predictions.size())), "", "Future refreshes will compare confidence, status, line movement, and alert changes."}
                };
            }

            std::map<std::string, const aegis::Game*> before_games;
            std::map<std::string, const aegis::Game*> after_games;
            for (const aegis::Game& game : before.games)
                before_games[game.id] = &game;
            for (const aegis::Game& game : after.games)
                after_games[game.id] = &game;

            int added = 0;
            int removed = 0;
            int status_changes = 0;
            int source_changes = 0;
            int line_moves = 0;
            for (const auto& entry : after_games)
            {
                const aegis::Game& game = *entry.second;
                const auto before_it = before_games.find(entry.first);
                if (before_it == before_games.end())
                {
                    ++added;
                }
                else
                {
                    const aegis::Game& previous = *before_it->second;
                    if (previous.status_key != game.status_key || previous.clock != game.clock || previous.status_label != game.status_label)
                        ++status_changes;
                    if (previous.odds_match_status != game.odds_match_status || previous.freshness_state != game.freshness_state || previous.source_note != game.source_note)
                        ++source_changes;
                }
                for (const aegis::BetLink& link : game.bet_links)
                {
                    const std::string move = aegis::Lower(link.movement);
                    if (!move.empty() && move.find("no movement") == std::string::npos && move.find("first") == std::string::npos)
                        ++line_moves;
                }
            }
            for (const auto& entry : before_games)
            {
                if (after_games.find(entry.first) == after_games.end())
                    ++removed;
            }

            std::map<std::string, int> before_confidence;
            for (const aegis::Prediction& prediction : before.predictions)
                before_confidence[prediction.game_id + "|" + prediction.market + "|" + prediction.pick] = prediction.confidence_value;

            int confidence_up = 0;
            int confidence_down = 0;
            int confidence_changed = 0;
            for (const aegis::Prediction& prediction : after.predictions)
            {
                const std::string key = prediction.game_id + "|" + prediction.market + "|" + prediction.pick;
                const auto it = before_confidence.find(key);
                if (it == before_confidence.end())
                    continue;
                const int delta = prediction.confidence_value - it->second;
                if (std::abs(delta) < 3)
                    continue;
                ++confidence_changed;
                if (delta > 0)
                    ++confidence_up;
                else
                    ++confidence_down;
            }

            const int alert_delta = static_cast<int>(after.alerts.size()) - static_cast<int>(before.alerts.size());
            const int book_delta = CountBookLinesInState(after) - CountBookLinesInState(before);
            const int exchange_delta = CountExchangeLinksInState(after) - CountExchangeLinksInState(before);
            const std::string event_value = std::string(added >= removed ? "+" : "") + std::to_string(added - removed);
            const std::string book_value = std::string(book_delta >= 0 ? "+" : "") + std::to_string(book_delta);
            const std::string exchange_value = std::string(exchange_delta >= 0 ? "+" : "") + std::to_string(exchange_delta);
            const std::string alert_value = std::string(alert_delta >= 0 ? "+" : "") + std::to_string(alert_delta);

            return {
                {"Event slate", "", event_value, "", std::to_string(added) + " added / " + std::to_string(removed) + " removed since the previous refresh."},
                {"Game status", "", std::to_string(status_changes), "", "Clock, live/final/upcoming, or status labels changed."},
                {"Source state", "", std::to_string(source_changes), "", "Freshness, fallback, odds-match, or provider-source state changed."},
                {"Confidence", "", std::to_string(confidence_changed), "", std::to_string(confidence_up) + " up / " + std::to_string(confidence_down) + " down by at least 3 points."},
                {"Line movement", "", std::to_string(line_moves), "", "Current provider links carrying a movement label after local snapshot comparison."},
                {"Book lines", "", book_value, "", "Change in matched sportsbook line count."},
                {"Exchange links", "", exchange_value, "", "Change in manual exchange/provider link count."},
                {"Alerts", "", alert_value, "", "Change in high-signal alert rows on the board."}
            };
        }

        std::vector<aegis::InfoItem> RefreshChangeRows() const
        {
            if (!last_change_summary_.empty())
                return last_change_summary_;
            return {
                {"Waiting", "", "No baseline", "", "Run a direct sports refresh to capture the first before/after change summary."}
            };
        }

        std::vector<aegis::InfoItem> ProviderHealthRows() const
        {
            const int live = CountStatusValue("live");
            const int upcoming = CountStatusValue("scheduled");
            const int book_lines = CountAvailableBookLines();
            return {
                {"Scoreboard", "", state_.source_badge, "", live > 0 || upcoming > 0 ? "Direct scoreboard refresh is returning events." : "No live/upcoming events are currently available."},
                {"Odds API", "", config_.odds_api_key.empty() ? "Needs key" : "Saved", "", config_.odds_api_key.empty() ? "Save and validate a key to enable direct book comparison." : "Encrypted key is available for refreshes."},
                {"Kalshi", "", config_.kalshi_key_id.empty() ? "Linked public" : "Credentials saved", "", "Kalshi remains manual/open-provider only; no automatic order placement."},
                {"Book lines", "", std::to_string(book_lines), "", book_lines > 0 ? "Matched sportsbook lines are available this refresh." : "No direct sportsbook lines matched this refresh."},
                {"Matched games", "", std::to_string(CountOddsMatchedGames()), "", "Scoreboard games mapped to direct odds events on the latest refresh."},
                {"Needs review", "", std::to_string(CountOddsIssueGames()), "", "Games with no odds event, unsupported sport mapping, or fallback source state."}
            };
        }

        std::vector<aegis::InfoItem> RefreshTelemetryRows() const
        {
            const int total_events = static_cast<int>(state_.games.size());
            const int total_predictions = static_cast<int>(state_.predictions.size());
            return {
                {"App version", "", AppVersionLabel(), "", "Displayed in UI, reports, package notes, and release audit."},
                {"Last refresh", "", last_refresh_label_, "", AgeLabel(SecondsSinceLastRefresh()) + "."},
                {"Refresh latency", "", last_refresh_elapsed_ms_ > 0 ? std::to_string(last_refresh_elapsed_ms_) + " ms" : "--", "", "Measured around the native sports board rebuild in the desktop app."},
                {"Cadence", "", std::to_string(config_.refresh_seconds) + "s", "", refresh_in_flight_ ? "A refresh is currently running." : "Background refresh uses this timer."},
                {"Events", "", std::to_string(total_events), "", std::to_string(CountStatusValue("live")) + " live / " + std::to_string(CountStatusValue("scheduled")) + " upcoming / " + std::to_string(CountStatusValue("final")) + " final."},
                {"Predictions", "", std::to_string(total_predictions), "", "Rows rebuilt from current scoreboard and market context."},
                {"Health log", "", FileSizeLabel(ProviderHealthFile()), "", "Provider health snapshots retained locally for source debugging."}
            };
        }

        std::vector<aegis::InfoItem> KalshiHealthRows() const
        {
            const bool saved = !aegis::Trim(config_.kalshi_key_id).empty() || !aegis::Trim(config_.kalshi_private_key).empty();
            return {
                {"Storage", "", saved ? "Encrypted" : "Empty", "", saved ? "Kalshi credentials are stored with Windows user protection." : "No Kalshi credentials are saved."},
                {"Access mode", "", "Read-only links", "", "Aegis can stage previews and open Kalshi. It does not automatically place real-money orders."},
                {"Validation", "", kalshi_status_, "", kalshi_detail_},
                {"Execution guard", "", "Manual only", "", "Order previews require the user to open the provider and confirm outside Aegis."}
            };
        }

        std::vector<aegis::InfoItem> ResponsibleModeRows() const
        {
            return {
                {"No auto execution", "", "Informational only", "", "Aegis explains probabilities and uncertainty without placing wagers."},
                {"Ticket limit", "", FormatMoneyText(config_.max_ticket_amount), "", "Preview tickets are blocked above this local limit."},
                {"Daily exposure", "", FormatMoneyText(TodayPreviewExposure()) + " / " + FormatMoneyText(config_.daily_exposure_limit), "", "Local ticket previews are blocked after this daily ledger limit."},
                {"Confidence floor", "", std::to_string(config_.min_ticket_confidence) + "%", "", "Ticket preview is blocked below this model threshold."},
                {"Paper mode", "", config_.paper_only_mode ? "On" : "Off", "", "When enabled, live provider handoff is disabled."},
                {"Missing data penalty", "", "Always applied", "", "Confidence is clipped when injuries, fatigue, advanced metrics, or lineup feeds are unavailable."}
            };
        }

        std::vector<aegis::InfoItem> DataTrustRows() const
        {
            return {
                {"Trust score", "", std::to_string(DataTrustScore()) + "%", "", "Composite of scoreboard freshness, market coverage, provider state, and local refresh age."},
                {"Freshness", "", IsBoardStale() ? "Stale" : "Fresh", "", "Last successful refresh was " + AgeLabel(SecondsSinceLastRefresh()) + "."},
                {"Scoreboard", "", state_.source_badge, "", state_.source_label},
                {"Book lines", "", std::to_string(CountAvailableBookLines()), "", CountAvailableBookLines() > 0 ? "Direct matched sportsbook lines are available this refresh." : "Add or validate an Odds API key for direct book comparison."},
                {"Odds matching", "", std::to_string(CountOddsMatchedGames()) + " matched", "", std::to_string(CountOddsIssueGames()) + " games need manual/provider review."},
                {"Fallback guard", "", state_.source_badge.find("Fallback") == std::string::npos ? "Clear" : "Fallback", "", "Fallback boards are labeled and lower the trust score."},
                {"Local rebuild", "", "Native", "", "Sports data and confidence refresh inside the desktop app from provider hosts."}
            };
        }

        std::vector<aegis::InfoItem> NotificationRows() const
        {
            std::vector<aegis::InfoItem> rows;
            rows.push_back({"Refresh", "", DataTrustLabel(), "", "Last refresh " + AgeLabel(SecondsSinceLastRefresh()) + ". " + status_, "", "", "", "", "", "", "", config_.notifications_enabled ? "On" : "Off"});
            rows.push_back({"Watchlist", "", std::to_string(watchlist_ids_.size()), "", watchlist_ids_.empty() ? "No pinned markets yet." : "Pinned markets are monitored against the current direct-source slate."});
            rows.push_back({"Risk controls", "", config_.paper_only_mode ? "Paper only" : "Preview enabled", "", "Ticket limit " + FormatMoneyText(config_.max_ticket_amount) + " / confidence floor " + std::to_string(config_.min_ticket_confidence) + "%. Exposure " + FormatMoneyText(TodayPreviewExposure()) + " / " + FormatMoneyText(config_.daily_exposure_limit) + "."});
            rows.push_back({"Alert rules", "", std::to_string(config_.alert_confidence_threshold) + "%+", "", config_.alert_watchlist_only ? "Only watched markets create alert journal entries." : (config_.alert_line_movement_only ? "Only line movement creates alert journal entries." : "Board alerts and high-confidence picks are logged locally.")});
            for (const aegis::InfoItem& journal : ReadNotificationItems(5))
            {
                rows.push_back(journal);
                if (rows.size() >= 8)
                    return rows;
            }
            for (const aegis::InfoItem& alert : state_.alerts)
            {
                aegis::InfoItem row = alert;
                row.name = row.name.empty() ? "Board alert" : row.name;
                row.value = row.value.empty() ? row.tag : row.value;
                rows.push_back(row);
                if (rows.size() >= 8)
                    break;
            }
            return rows;
        }

        std::vector<aegis::InfoItem> AlertRuleRows() const
        {
            return {
                {"Journal", "", config_.notifications_enabled ? "Enabled" : "Disabled", "", "When enabled, refreshes write high-signal alerts to notifications.tsv."},
                {"Confidence trigger", "", std::to_string(config_.alert_confidence_threshold) + "%+", "", "Predictions at or above this confidence are logged as high-confidence alerts."},
                {"Scope", "", config_.alert_watchlist_only ? "Watched only" : "Full board", "", "Watchlist-only mode keeps the journal focused on pinned markets."},
                {"Movement filter", "", config_.alert_line_movement_only ? "Line movement" : "All high-signal", "", "Line-movement mode suppresses general board alerts."},
                {"Favorites", "", std::to_string(CountFavoriteGames()), "", "Favorite teams and leagues are available as a board filter."},
                {"Stored rows", "", std::to_string(static_cast<int>(ReadNotificationItems(200).size())), "", "Recent local notification rows currently readable from disk."}
            };
        }

        std::vector<aegis::InfoItem> NotificationSummaryRows() const
        {
            int high_confidence = 0;
            int movement = 0;
            int alerts = 0;
            for (const aegis::InfoItem& item : ReadNotificationItems(200))
            {
                const std::string tag = aegis::Lower(item.tag + " " + item.name + " " + item.detail);
                if (tag.find("high confidence") != std::string::npos)
                    ++high_confidence;
                else if (tag.find("movement") != std::string::npos || tag.find("moved") != std::string::npos)
                    ++movement;
                else
                    ++alerts;
            }
            return {
                {"High confidence", "", std::to_string(high_confidence), "", "Model alerts generated from the configured confidence threshold."},
                {"Line movement", "", std::to_string(movement), "", "Movement-style alerts detected in the local journal."},
                {"Board alerts", "", std::to_string(alerts), "", "Other high-signal rows retained locally."},
                {"Current alerts", "", std::to_string(static_cast<int>(state_.alerts.size())), "", "Rows from the latest direct-source refresh."}
            };
        }

        std::vector<aegis::InfoItem> ExposureSummaryRows() const
        {
            const std::vector<ExposureRow> rows = ReadExposureRows(600);
            const std::string today = TodayDateLabel();
            double today_total = 0.0;
            double all_total = 0.0;
            int today_count = 0;
            int paper = 0;
            int handoff = 0;
            for (const ExposureRow& row : rows)
            {
                all_total += row.amount;
                if (row.date == today)
                {
                    today_total += row.amount;
                    ++today_count;
                }
                const std::string mode = aegis::Lower(row.mode);
                if (mode.find("handoff") != std::string::npos)
                    ++handoff;
                else
                    ++paper;
            }
            const double remaining = std::max(0.0, config_.daily_exposure_limit - today_total);
            return {
                {"Today", "", FormatMoneyText(today_total), "", std::to_string(today_count) + " entries logged today."},
                {"Remaining", "", FormatMoneyText(remaining), "", "Available before the configured daily exposure limit blocks previews."},
                {"Daily limit", "", FormatMoneyText(config_.daily_exposure_limit), "", "Configured under Settings."},
                {"All-time previewed", "", FormatMoneyText(all_total), "", std::to_string(static_cast<int>(rows.size())) + " local ledger entries."},
                {"Paper entries", "", std::to_string(paper), "", "Local paper submissions only."},
                {"Manual handoffs", "", std::to_string(handoff), "", "Provider-open events requiring outside confirmation."}
            };
        }

        std::vector<aegis::InfoItem> CommandBriefRows() const
        {
            const aegis::Prediction* top = nullptr;
            for (const int index : VisiblePredictionIndexes())
            {
                const aegis::Prediction& prediction = state_.predictions[static_cast<size_t>(index)];
                if (prediction.status_key == "final")
                    continue;
                if (top == nullptr || prediction.confidence_value > top->confidence_value)
                    top = &prediction;
            }

            std::string action_value = "Monitor";
            std::string action_detail = "No actionable model row matches the active filters.";
            if (top != nullptr)
            {
                action_value = top->confidence + " " + top->market;
                action_detail = top->matchup + " / " + top->pick;
            }

            const std::string alert_value = state_.alerts.empty() ? "Quiet" : std::to_string(state_.alerts.size()) + " active";
            const std::string alert_detail = state_.alerts.empty() ? "No current board alerts after the latest refresh." : FirstNonEmpty(state_.alerts.front().detail, state_.alerts.front().value, state_.alerts.front().tag);
            const std::string focus_value = std::to_string(CountFavoriteGames()) + " favorites";
            const std::string focus_detail = config_.favorite_teams.empty() && config_.favorite_leagues.empty()
                ? "Add favorite teams or leagues in Settings to create a focused board."
                : "Favorites filter can isolate your saved teams and leagues.";

            return {
                {"Next best read", "", action_value, "", action_detail},
                {"Alert posture", "", alert_value, "", alert_detail},
                {"Focus board", "", focus_value, "", focus_detail}
            };
        }

        std::vector<aegis::InfoItem> SetupChecklistRows() const
        {
            return {
                {"Auth", "", authenticated_ ? "Signed in" : "Needs sign-in", "", authenticated_ ? "Website auth bridge has accepted the current session." : "Sign in to unlock the native sports terminal."},
                {"Odds API", "", config_.odds_api_key.empty() ? "Needs key" : "Saved", "", config_.odds_api_key.empty() ? "Enables direct sportsbook line comparison and movement tracking." : "Encrypted Odds API key is available for refreshes."},
                {"Kalshi", "", config_.kalshi_key_id.empty() ? "Public links" : "Credentials saved", "", "Used for manual provider handoff only; Aegis does not auto-submit orders."},
                {"Data adapters", "", (aegis::Trim(config_.injury_feed_url + config_.lineup_feed_url + config_.news_feed_url + config_.props_feed_url).empty() ? "Optional gaps" : "Configured"), "", "Injury, lineup, news, and prop feeds can be configured without faking unavailable data."},
                {"Risk limits", "", config_.paper_only_mode ? "Paper only" : "Preview mode", "", "Set amount, confidence floor, and confirmation requirements before staging tickets."},
                {"Disclosures", "", (config_.responsible_use_accepted && config_.legal_location_confirmed) ? "Acknowledged" : "Needs review", "", "Responsible-use and legal/location reminders are tracked in Settings."},
                {"Alert rules", "", std::to_string(config_.alert_confidence_threshold) + "%+", "", "High-confidence predictions and board alerts are written to the local notification journal."},
                {"Refresh cadence", "", std::to_string(config_.refresh_seconds) + "s", "", "The scoreboard and model rebuild automatically on this cadence."}
            };
        }

        std::vector<aegis::InfoItem> DiagnosticsRows() const
        {
            return {
                {"App version", "", AppVersionLabel(), "", "Build metadata used by diagnostics and release audits."},
                {"App data", "", "LocalAppData", "", aegis::AppDataDirectory().string()},
                {"Diagnostics", "", FileSizeLabel(aegis::AppDataDirectory() / "diagnostics.log"), "", "Auth, refresh, and provider sync events."},
                {"Diagnostic bundle", "", FileSizeLabel(DiagnosticBundleDirectory() / "summary.txt"), "", "Safe support export with redacted settings and source health."},
                {"Provider health", "", FileSizeLabel(ProviderHealthFile()), "", "Refresh, source, and adapter health snapshots."},
                {"Market snapshots", "", FileSizeLabel(aegis::AppDataDirectory() / "market-snapshots.tsv"), "", "Matched sportsbook prices retained for movement labels."},
                {"Prediction audit", "", FileSizeLabel(aegis::AppDataDirectory() / "prediction-audit.tsv"), "", "Prediction samples and final-game grading trail."},
                {"Notifications", "", FileSizeLabel(NotificationFile()), "", "Local alert journal created from refresh events and model thresholds."},
                {"Decision journal", "", FileSizeLabel(ScenarioJournalFile()), "", "Saved scenario reads with probability, stake, provider price, and EV."},
                {"CSV report", "", FileSizeLabel(aegis::AppDataDirectory() / "aegis-report.csv"), "", "Exportable report generated from Reports."},
                {"PDF report", "", FileSizeLabel(aegis::AppDataDirectory() / "aegis-report.pdf"), "", "Simple printable PDF report generated from Reports."},
                {"Slip audit", "", FileSizeLabel(aegis::AppDataDirectory() / "slip-audit.tsv"), "", "Paper tickets and manual provider handoffs."},
                {"Exposure ledger", "", FileSizeLabel(ExposureLedgerFile()), "", "Daily preview exposure used by local risk controls."},
                {"Watchlist", "", FileSizeLabel(WatchlistFile()), "", "Pinned game ids used by the ticket preview workspace."},
                {"Retention", "", "Enabled", "", "Local notification, decision, exposure, slip, market, and audit journals prune to bounded recent history."}
            };
        }

        std::vector<aegis::InfoItem> DailyReportRows() const
        {
            const std::vector<AuditRow> audit = ReadAuditRows(600);
            int graded = 0;
            int wins = 0;
            for (const AuditRow& row : audit)
            {
                if (row.result == "win" || row.result == "loss")
                {
                    ++graded;
                    if (row.result == "win")
                        ++wins;
                }
            }
            const std::string win_rate = graded > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(wins) * 100.0 / graded))) + "%" : "--";
            return {
                {"Data trust", "", std::to_string(DataTrustScore()) + "%", "", "Current board trust is " + DataTrustLabel() + " after the latest native refresh."},
                {"Board", "", std::to_string(static_cast<int>(state_.games.size())) + " games", "", std::to_string(CountStatusValue("live")) + " live / " + std::to_string(CountStatusValue("scheduled")) + " upcoming / " + std::to_string(CountStatusValue("final")) + " final."},
                {"Visible picks", "", std::to_string(static_cast<int>(VisiblePredictionIndexes().size())), "", "After sport, search, confidence, favorite, watchlist, line, and actionable filters."},
                {"Model grade", "", win_rate, "", std::to_string(graded) + " locally graded prediction samples."},
                {"Exposure today", "", FormatMoneyText(TodayPreviewExposure()), "", "Daily limit " + FormatMoneyText(config_.daily_exposure_limit) + "."},
                {"Scenario reads", "", std::to_string(static_cast<int>(ReadScenarioJournalRows(1000).size())), "", "Saved local decision snapshots from Scenario Lab."},
                {"Notifications", "", std::to_string(static_cast<int>(ReadNotificationItems(200).size())), "", "Recent local alert journal rows."}
            };
        }

        std::filesystem::path MarketSnapshotFile() const
        {
            return aegis::AppDataDirectory() / "market-snapshots.tsv";
        }

        std::vector<MarketSnapshotRow> ReadMarketSnapshotRows(int max_rows = 800) const
        {
            std::ifstream file(MarketSnapshotFile());
            std::vector<MarketSnapshotRow> rows;
            if (!file)
                return rows;
            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 6)
                    continue;
                MarketSnapshotRow row;
                row.key = parts[0];
                row.matchup = parts[1];
                row.book = parts[2];
                row.line = parts[3];
                row.price = parts[4];
                row.seen_at = parts[5];
                rows.push_back(row);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            return rows;
        }

        std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> MarketSnapshotPairs(int max_rows = 800) const
        {
            const std::vector<MarketSnapshotRow> rows = ReadMarketSnapshotRows(max_rows);
            std::map<std::string, std::pair<MarketSnapshotRow, MarketSnapshotRow>> grouped;
            for (const MarketSnapshotRow& row : rows)
            {
                auto it = grouped.find(row.key);
                if (it == grouped.end())
                    grouped[row.key] = { row, row };
                else
                    it->second.second = row;
            }
            std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> out;
            out.reserve(grouped.size());
            for (const auto& entry : grouped)
                out.push_back(entry.second);
            std::sort(out.begin(), out.end(), [](const auto& left, const auto& right) {
                return left.second.seen_at > right.second.seen_at;
            });
            return out;
        }

        bool SnapshotMoved(const MarketSnapshotRow& first, const MarketSnapshotRow& last) const
        {
            return first.line != last.line || first.price != last.price;
        }

        std::string SnapshotMoveLabel(const MarketSnapshotRow& first, const MarketSnapshotRow& last) const
        {
            if (!SnapshotMoved(first, last))
                return "No movement";
            if (first.line != last.line && first.price != last.price)
                return "Line and price";
            if (first.line != last.line)
                return "Line";
            return "Price";
        }

        std::vector<aegis::InfoItem> MarketSnapshotSummaryRows() const
        {
            const std::vector<MarketSnapshotRow> rows = ReadMarketSnapshotRows(1200);
            const std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> pairs = MarketSnapshotPairs(1200);
            std::set<std::string> books;
            int moved = 0;
            for (const MarketSnapshotRow& row : rows)
                books.insert(row.book);
            for (const auto& pair : pairs)
            {
                if (SnapshotMoved(pair.first, pair.second))
                    ++moved;
            }
            const std::string latest = rows.empty() ? "--" : rows.back().seen_at;
            return {
                {"Snapshot rows", "", std::to_string(static_cast<int>(rows.size())), "", "Recent sportsbook snapshots retained from direct odds refreshes."},
                {"Tracked markets", "", std::to_string(static_cast<int>(pairs.size())), "", "Unique game/provider/market keys in the retained snapshot window."},
                {"Books", "", std::to_string(static_cast<int>(books.size())), "", "Unique sportsbook providers captured locally."},
                {"Moved markets", "", std::to_string(moved), "", "Markets whose first and latest retained line or price differ."},
                {"Latest sample", "", latest, "", "Most recent local market snapshot timestamp."},
                {"Source", "", config_.odds_api_key.empty() ? "Scoreboard-only" : "Odds API", "", config_.odds_api_key.empty() ? "Add an Odds API key for deeper matched book history." : "Matched sportsbook lines are appended during refresh."}
            };
        }

        std::vector<CalibrationBucket> BuildCalibrationBuckets() const
        {
            std::vector<CalibrationBucket> buckets = {
                {"50-59%"},
                {"60-69%"},
                {"70-79%"},
                {"80%+"}
            };
            for (const AuditRow& row : ReadAuditRows(1200))
            {
                const int confidence = std::clamp(row.confidence, 0, 100);
                int bucket_index = 0;
                if (confidence >= 80)
                    bucket_index = 3;
                else if (confidence >= 70)
                    bucket_index = 2;
                else if (confidence >= 60)
                    bucket_index = 1;
                CalibrationBucket& bucket = buckets[static_cast<size_t>(bucket_index)];
                ++bucket.samples;
                bucket.confidence_sum += confidence;
                if (row.result == "win" || row.result == "loss")
                {
                    ++bucket.graded;
                    if (row.result == "win")
                        ++bucket.wins;
                }
            }
            return buckets;
        }

        std::vector<aegis::InfoItem> CalibrationSummaryRows() const
        {
            std::vector<aegis::InfoItem> rows;
            for (const CalibrationBucket& bucket : BuildCalibrationBuckets())
            {
                const std::string win_rate = bucket.graded > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.wins) * 100.0 / bucket.graded))) + "%" : "--";
                const std::string avg_conf = bucket.samples > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.confidence_sum) / bucket.samples))) + "%" : "--";
                rows.push_back({bucket.label, "", win_rate, "", std::to_string(bucket.samples) + " samples / " + std::to_string(bucket.graded) + " graded / avg " + avg_conf});
            }
            return rows;
        }

        std::vector<ProviderQuality> BuildProviderQualityRows() const
        {
            std::map<std::string, ProviderQuality> quality;
            std::map<std::string, std::set<std::string>> games_by_book;
            for (const aegis::Game& game : state_.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    const std::string book = FirstNonEmpty(link.title, link.provider_key, link.kind, "Provider");
                    if (book.empty())
                        continue;
                    ProviderQuality& row = quality[book];
                    row.book = book;
                    if (link.available)
                        ++row.live_lines;
                    games_by_book[book].insert(game.id);
                }
            }
            for (const auto& pair : MarketSnapshotPairs(1500))
            {
                const std::string book = pair.second.book.empty() ? "Provider" : pair.second.book;
                ProviderQuality& row = quality[book];
                row.book = book;
                ++row.snapshots;
                if (SnapshotMoved(pair.first, pair.second))
                    ++row.moved;
            }
            std::vector<ProviderQuality> rows;
            rows.reserve(quality.size());
            for (auto& entry : quality)
            {
                entry.second.games = static_cast<int>(games_by_book[entry.first].size());
                rows.push_back(entry.second);
            }
            std::sort(rows.begin(), rows.end(), [](const ProviderQuality& left, const ProviderQuality& right) {
                const int left_score = left.live_lines * 4 + left.snapshots + left.moved * 5 + left.games * 3;
                const int right_score = right.live_lines * 4 + right.snapshots + right.moved * 5 + right.games * 3;
                if (left_score != right_score)
                    return left_score > right_score;
                return left.book < right.book;
            });
            if (rows.size() > 10)
                rows.resize(10);
            return rows;
        }

        std::vector<aegis::InfoItem> ProviderQualitySummaryRows() const
        {
            std::vector<aegis::InfoItem> rows;
            const std::vector<ProviderQuality> providers = BuildProviderQualityRows();
            for (size_t i = 0; i < providers.size() && i < 6; ++i)
            {
                const ProviderQuality& provider = providers[i];
                const int score = std::clamp(provider.live_lines * 4 + provider.snapshots + provider.moved * 5 + provider.games * 3, 0, 100);
                rows.push_back({provider.book, "", std::to_string(score) + "/100", "", std::to_string(provider.live_lines) + " live lines / " + std::to_string(provider.snapshots) + " snapshots / " + std::to_string(provider.moved) + " moved"});
            }
            if (rows.empty())
                rows.push_back({"Provider coverage", "", "Waiting", "", "Provider quality appears after sportsbook links or odds snapshots are available."});
            return rows;
        }

        std::vector<aegis::InfoItem> ProviderSportQualityRows() const
        {
            struct Row
            {
                std::string book;
                std::string sport;
                int lines = 0;
                int available = 0;
                int games = 0;
            };
            std::map<std::string, Row> rows_by_key;
            std::map<std::string, std::set<std::string>> games_by_key;
            for (const aegis::Game& game : state_.games)
            {
                for (const aegis::BetLink& link : game.bet_links)
                {
                    const std::string book = FirstNonEmpty(link.title, link.provider_key, link.kind, "Provider");
                    const std::string sport = FirstNonEmpty(game.sport_group, game.league, "Sports");
                    const std::string key = book + "|" + sport;
                    Row& row = rows_by_key[key];
                    row.book = book;
                    row.sport = sport;
                    ++row.lines;
                    if (link.available)
                        ++row.available;
                    games_by_key[key].insert(game.id);
                }
            }
            std::vector<Row> sorted;
            for (auto& entry : rows_by_key)
            {
                entry.second.games = static_cast<int>(games_by_key[entry.first].size());
                sorted.push_back(entry.second);
            }
            std::sort(sorted.begin(), sorted.end(), [](const Row& left, const Row& right) {
                const int left_score = left.available * 5 + left.games * 2 + left.lines;
                const int right_score = right.available * 5 + right.games * 2 + right.lines;
                if (left_score != right_score)
                    return left_score > right_score;
                return left.book + left.sport < right.book + right.sport;
            });
            std::vector<aegis::InfoItem> out;
            for (size_t i = 0; i < sorted.size() && i < 8; ++i)
            {
                const Row& row = sorted[i];
                out.push_back({row.book, "", row.sport, "", std::to_string(row.available) + " live / " + std::to_string(row.lines) + " rows / " + std::to_string(row.games) + " games"});
            }
            return out;
        }

        std::vector<aegis::InfoItem> BacktestRowsByMarket() const
        {
            struct Bucket
            {
                int samples = 0;
                int graded = 0;
                int wins = 0;
                int confidence_sum = 0;
            };
            std::map<std::string, Bucket> buckets;
            for (const AuditRow& row : ReadAuditRows(1500))
            {
                const std::string market = row.market.empty() ? "Unknown" : row.market;
                Bucket& bucket = buckets[market];
                ++bucket.samples;
                bucket.confidence_sum += row.confidence;
                if (row.result == "win" || row.result == "loss")
                {
                    ++bucket.graded;
                    if (row.result == "win")
                        ++bucket.wins;
                }
            }
            std::vector<std::pair<std::string, Bucket>> sorted(buckets.begin(), buckets.end());
            std::sort(sorted.begin(), sorted.end(), [](const auto& left, const auto& right) {
                if (left.second.graded != right.second.graded)
                    return left.second.graded > right.second.graded;
                return left.first < right.first;
            });
            std::vector<aegis::InfoItem> rows;
            for (size_t i = 0; i < sorted.size() && i < 8; ++i)
            {
                const Bucket& bucket = sorted[i].second;
                const std::string win_rate = bucket.graded > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.wins) * 100.0 / bucket.graded))) + "%" : "--";
                const std::string avg_conf = bucket.samples > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(bucket.confidence_sum) / bucket.samples))) + "%" : "--";
                rows.push_back({sorted[i].first, "", win_rate, "", std::to_string(bucket.samples) + " samples / " + std::to_string(bucket.graded) + " graded / avg confidence " + avg_conf});
            }
            return rows;
        }

        std::vector<aegis::InfoItem> BankrollRows() const
        {
            const std::vector<ExposureRow> exposure = ReadExposureRows(1000);
            const std::vector<DecisionJournalRow> journal = ReadScenarioJournalRows(1000);
            double paper_total = 0.0;
            int paper_count = 0;
            for (const ExposureRow& row : exposure)
            {
                paper_total += row.amount;
                ++paper_count;
            }
            double positive_ev = 0.0;
            double negative_ev = 0.0;
            for (const DecisionJournalRow& row : journal)
            {
                if (row.expected_return >= 0.0)
                    positive_ev += row.expected_return;
                else
                    negative_ev += row.expected_return;
            }
            const double remaining = std::max(0.0, config_.bankroll_starting_amount - TodayPreviewExposure());
            return {
                {"Mode", "", config_.bankroll_analytics_enabled ? "Enabled" : "Disabled", "", "Optional analytics only; no bankroll controls appear on the main board."},
                {"Starting bankroll", "", FormatMoneyText(config_.bankroll_starting_amount), "", "Configured in Settings for reporting and exposure context."},
                {"Today available", "", FormatMoneyText(remaining), "", "Starting bankroll minus today's local preview exposure."},
                {"Paper exposure", "", FormatMoneyText(paper_total), "", std::to_string(paper_count) + " local paper/manual exposure rows."},
                {"Positive EV saved", "", FormatSignedMoneyText(positive_ev), "", "Sum of saved positive scenario EV reads."},
                {"Negative EV saved", "", FormatSignedMoneyText(negative_ev), "", "Sum of saved negative scenario EV reads."}
            };
        }

        std::filesystem::path PredictionAuditFile() const
        {
            return aegis::AppDataDirectory() / "prediction-audit.tsv";
        }

        std::vector<std::string> SplitTabsLocal(const std::string& line) const
        {
            std::vector<std::string> parts;
            std::stringstream stream(line);
            std::string part;
            while (std::getline(stream, part, '\t'))
                parts.push_back(part);
            return parts;
        }

        std::vector<AuditRow> ReadAuditRows(int max_rows = 400) const
        {
            std::ifstream file(PredictionAuditFile());
            std::vector<AuditRow> rows;
            if (!file)
                return rows;

            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 8)
                    continue;
                AuditRow row;
                row.time = parts[0];
                row.game_id = parts[1];
                row.matchup = parts[2];
                row.market = parts[3];
                row.pick = parts[4];
                row.confidence = std::clamp(std::atoi(parts[5].c_str()), 0, 100);
                row.actual = parts[6];
                row.result = parts[7];
                rows.push_back(row);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            return rows;
        }

        std::vector<aegis::InfoItem> ModelAuditRows() const
        {
            const std::vector<AuditRow> rows = ReadAuditRows();
            int graded = 0;
            int wins = 0;
            int open = 0;
            int high_confidence = 0;
            int high_confidence_wins = 0;
            int confidence_sum = 0;
            for (const AuditRow& row : rows)
            {
                confidence_sum += row.confidence;
                if (row.result == "win" || row.result == "loss")
                {
                    ++graded;
                    if (row.result == "win")
                        ++wins;
                    if (row.confidence >= 65)
                    {
                        ++high_confidence;
                        if (row.result == "win")
                            ++high_confidence_wins;
                    }
                }
                else
                {
                    ++open;
                }
            }
            const int samples = static_cast<int>(rows.size());
            const std::string win_rate = graded > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(wins) * 100.0 / graded))) + "%" : "--";
            const std::string avg_confidence = samples > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(confidence_sum) / samples))) + "%" : "--";
            const std::string high_rate = high_confidence > 0 ? std::to_string(static_cast<int>(std::round(static_cast<double>(high_confidence_wins) * 100.0 / high_confidence))) + "%" : "--";
            return {
                {"Samples", "", std::to_string(samples), "", "Recent local prediction samples retained from refreshes."},
                {"Graded", "", std::to_string(graded), "", "Only final games with resolved sides count as graded."},
                {"Win rate", "", win_rate, "", "Directional model result on completed audited samples."},
                {"Avg confidence", "", avg_confidence, "", "Mean displayed confidence across recent samples."},
                {"Open samples", "", std::to_string(open), "", "Predictions still awaiting a final outcome."},
                {"High-confidence grade", "", high_rate, "", "Win rate on graded rows at 65% confidence or higher."}
            };
        }

        void RenderAuditHistoryPanel()
        {
            const std::vector<AuditRow> rows = ReadAuditRows(120);
            ImGui::BeginChild("audit_history", ImVec2(0, 330.0f), true);
            CardHeader("Prediction History", "Local model audit trail", active_view_);
            if (rows.empty())
            {
                EmptyState("No audit rows yet", "The history will populate as native refreshes append model samples.");
                ImGui::EndChild();
                return;
            }
            if (ImGui::BeginTable("audit_rows", 7, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Time", ImGuiTableColumnFlags_WidthFixed, 64.0f);
                ImGui::TableSetupColumn("Matchup", ImGuiTableColumnFlags_WidthStretch, 1.35f);
                ImGui::TableSetupColumn("Market", ImGuiTableColumnFlags_WidthFixed, 100.0f);
                ImGui::TableSetupColumn("Pick", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableSetupColumn("Conf", ImGuiTableColumnFlags_WidthFixed, 70.0f);
                ImGui::TableSetupColumn("Actual", ImGuiTableColumnFlags_WidthStretch, 0.8f);
                ImGui::TableSetupColumn("Result", ImGuiTableColumnFlags_WidthFixed, 76.0f);
                ImGui::TableHeadersRow();
                int rendered = 0;
                for (auto it = rows.rbegin(); it != rows.rend() && rendered < 12; ++it, ++rendered)
                {
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0); ImGui::TextUnformatted(it->time.c_str());
                    ImGui::TableSetColumnIndex(1); ImGui::TextUnformatted(it->matchup.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(it->market.c_str());
                    ImGui::TableSetColumnIndex(3); ImGui::TextUnformatted(it->pick.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::Text("%d%%", it->confidence);
                    ImGui::TableSetColumnIndex(5); ImGui::TextUnformatted(it->actual.c_str());
                    ImGui::TableSetColumnIndex(6);
                    if (it->result == "win")
                        TextGreen("win");
                    else if (it->result == "loss")
                        ImGui::TextColored(V4(1.0f, 0.45f, 0.35f, 1.0f), "loss");
                    else
                        TextMuted("open");
                }
                ImGui::EndTable();
            }
            ImGui::EndChild();
        }

        void RenderDiagnosticsPanel()
        {
            ImGui::BeginChild("diagnostics_panel", ImVec2(0, 330.0f), true);
            CardHeader("Data Source Diagnostics", "Local audit and export tools", active_view_);
            if (AegisButton("Export Report", ImVec2(142.0f, 38.0f), true))
                ExportWorkspaceReport();
            ImGui::SameLine();
            if (AegisButton("Open Data Folder", ImVec2(160.0f, 38.0f), false))
                aegis::OpenExternalUrl(aegis::AppDataDirectory().string());
            ImGui::SameLine();
            if (AegisButton("Clear Alerts", ImVec2(130.0f, 38.0f), false))
                ClearNotificationJournal();
            ImGui::SameLine();
            if (AegisButton("Refresh Now", ImVec2(130.0f, 38.0f), false))
                BeginSportsRefresh(false, "Diagnostics refresh", "Rechecking direct data providers and rebuilding confidence.");
            if (!export_status_.empty())
                TextGreen(export_status_.c_str());
            ImGui::Spacing();
            const std::vector<aegis::InfoItem> rows = DiagnosticsRows();
            const float cell_w = std::max(220.0f, (ImGui::GetContentRegionAvail().x - 12.0f) * 0.5f);
            for (size_t i = 0; i < rows.size(); ++i)
            {
                if (i % 2 != 0)
                    ImGui::SameLine();
                RenderInfoChip(rows[i], ImVec2(cell_w, 96.0f));
            }
            ImGui::EndChild();
        }

        std::string FormatMoneyText(double value) const
        {
            std::ostringstream stream;
            stream << "$" << std::fixed << std::setprecision(2) << std::max(0.0, value);
            return stream.str();
        }

        std::string FileSizeLabel(const std::filesystem::path& path) const
        {
            std::error_code ec;
            const auto size = std::filesystem::exists(path, ec) ? std::filesystem::file_size(path, ec) : 0;
            if (ec || size == 0)
                return "Empty";
            if (size < 1024)
                return std::to_string(size) + " B";
            if (size < 1024 * 1024)
                return std::to_string(size / 1024) + " KB";
            return std::to_string(size / (1024 * 1024)) + " MB";
        }

        std::string TsvField(std::string value) const
        {
            for (char& c : value)
            {
                if (c == '\t' || c == '\r' || c == '\n')
                c = ' ';
            }
            return aegis::Trim(value);
        }

        std::filesystem::path TempOutputPath(const std::filesystem::path& path) const
        {
            return std::filesystem::path(path.string() + ".tmp");
        }

        bool CommitTempFile(const std::filesystem::path& temp_path, const std::filesystem::path& final_path) const
        {
            if (!std::filesystem::exists(temp_path))
                return false;
            std::error_code ec;
            if (!final_path.parent_path().empty())
                std::filesystem::create_directories(final_path.parent_path(), ec);
            if (!MoveFileExW(temp_path.wstring().c_str(), final_path.wstring().c_str(), MOVEFILE_REPLACE_EXISTING | MOVEFILE_WRITE_THROUGH))
            {
                std::filesystem::remove(temp_path, ec);
                return false;
            }
            return true;
        }

        void PruneLocalTsvFile(const std::filesystem::path& path, size_t max_rows) const
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

            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream out(temp_path, std::ios::trunc);
            if (!out)
                return;
            const size_t start = rows.size() - max_rows;
            for (size_t i = start; i < rows.size(); ++i)
                out << rows[i] << '\n';
            out.close();
            CommitTempFile(temp_path, path);
        }

        std::filesystem::path NotificationFile() const
        {
            return aegis::AppDataDirectory() / "notifications.tsv";
        }

        std::filesystem::path ProviderHealthFile() const
        {
            return aegis::AppDataDirectory() / "provider-health.tsv";
        }

        std::filesystem::path DiagnosticBundleDirectory() const
        {
            return aegis::AppDataDirectory() / "diagnostic-bundle";
        }

        bool AlertMatchesWatchlist(const aegis::InfoItem& item, const aegis::SportsState& state) const
        {
            if (watchlist_ids_.empty())
                return false;
            const std::string haystack = aegis::Lower(item.name + " " + item.detail + " " + item.value + " " + item.tag);
            for (const std::string& id : watchlist_ids_)
            {
                if (!id.empty() && haystack.find(aegis::Lower(id)) != std::string::npos)
                    return true;
                for (const aegis::Game& game : state.games)
                {
                    if (game.id != id)
                        continue;
                    const std::string matchup = aegis::Lower(game.matchup + " " + game.away.name + " " + game.home.name + " " + game.away.abbr + " " + game.home.abbr);
                    std::stringstream tokens(matchup);
                    std::string token;
                    while (tokens >> token)
                    {
                        if (token.size() >= 3 && haystack.find(token) != std::string::npos)
                            return true;
                    }
                }
            }
            return false;
        }

        void AppendNotification(const std::string& kind, const std::string& title, const std::string& detail, const std::string& value)
        {
            const std::string key = aegis::Lower(kind + "|" + title + "|" + detail + "|" + value);
            if (!seen_notification_keys_.insert(key).second)
                return;
            std::ofstream file(NotificationFile(), std::ios::app);
            if (!file)
                return;
            file << TodayDateLabel() << '\t'
                 << aegis::NowTimeLabel() << '\t'
                 << TsvField(kind) << '\t'
                 << TsvField(title) << '\t'
                 << TsvField(value) << '\t'
                 << TsvField(detail) << '\n';
            file.close();
            PruneLocalTsvFile(NotificationFile(), 1000);
        }

        std::vector<aegis::InfoItem> ReadNotificationItems(int max_rows = 8) const
        {
            std::ifstream file(NotificationFile());
            std::vector<aegis::InfoItem> rows;
            if (!file)
                return rows;
            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 6)
                    continue;
                aegis::InfoItem row;
                row.time = parts[1];
                row.tag = parts[2];
                row.name = parts[3];
                row.value = parts[4];
                row.detail = parts[5];
                rows.push_back(row);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            std::reverse(rows.begin(), rows.end());
            return rows;
        }

        void ProcessRefreshNotifications(const aegis::SportsState& state)
        {
            if (!config_.notifications_enabled)
                return;

            int alert_count = 0;
            for (const aegis::InfoItem& alert : state.alerts)
            {
                const std::string lower = aegis::Lower(alert.name + " " + alert.detail + " " + alert.tag);
                if (config_.alert_line_movement_only && lower.find("line movement") == std::string::npos && lower.find("moved") == std::string::npos)
                    continue;
                const bool watched = AlertMatchesWatchlist(alert, state);
                if (config_.alert_watchlist_only && !watched)
                    continue;
                const bool high_signal =
                    lower.find("line movement") != std::string::npos ||
                    lower.find("moved") != std::string::npos ||
                    lower.find("live") != std::string::npos ||
                    lower.find("stale") != std::string::npos ||
                    lower.find("fallback") != std::string::npos ||
                    lower.find("alert") != std::string::npos ||
                    watched;
                if (!high_signal)
                    continue;
                AppendNotification("Alert", FirstNonEmpty(alert.name, alert.label, "Board alert"), alert.detail, FirstNonEmpty(alert.value, alert.tag, alert.time));
                if (++alert_count >= 4)
                    break;
            }

            int pick_count = 0;
            for (const aegis::Prediction& prediction : state.predictions)
            {
                if (prediction.status_key == "final" || prediction.confidence_value < config_.alert_confidence_threshold)
                    continue;
                if (config_.alert_watchlist_only && !IsWatched(prediction.game_id))
                    continue;
                AppendNotification("High confidence", prediction.matchup, prediction.pick + " / " + prediction.edge, prediction.confidence);
                if (++pick_count >= 4)
                    break;
            }
        }

        void AppendProviderHealthSnapshot(const std::string& reason)
        {
            std::ofstream file(ProviderHealthFile(), std::ios::app);
            if (!file)
                return;

            const std::string date = TodayDateLabel();
            const std::string time = aegis::NowTimeLabel();
            auto write_item = [&](const std::string& section, const aegis::InfoItem& item) {
                file << date << '\t'
                     << time << '\t'
                     << TsvField(reason) << '\t'
                     << TsvField(section) << '\t'
                     << TsvField(FirstNonEmpty(item.name, item.label, item.book, "Source")) << '\t'
                     << TsvField(FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds)) << '\t'
                     << TsvField(FirstNonEmpty(item.detail, item.latency, item.env, item.source)) << '\n';
            };

            for (const aegis::InfoItem& row : ProviderHealthRows())
                write_item("provider", row);
            for (const aegis::InfoItem& row : DataAdapterRows())
                write_item("adapter", row);
            for (const aegis::InfoItem& row : aegis::OptionalFeedSchemaRows())
                write_item("adapter_schema", row);
            for (const aegis::InfoItem& row : RefreshTelemetryRows())
                write_item("refresh", row);
            for (const aegis::InfoItem& row : ProviderRefreshRows())
                write_item("source_refresh", row);
            for (const aegis::InfoItem& row : state_.diagnostics)
                write_item("odds_diagnostic", row);
            for (const aegis::InfoItem& row : state_.provider_sports)
                write_item("provider_sport_status", row);

            file.close();
            PruneLocalTsvFile(ProviderHealthFile(), 2500);
        }

        std::vector<aegis::InfoItem> ProviderHealthHistoryRows(int max_rows = 10) const
        {
            std::ifstream file(ProviderHealthFile());
            std::vector<aegis::InfoItem> rows;
            if (!file)
            {
                rows.push_back({"No health history", "", "Waiting", "", "Refresh the board or validate feeds to create provider-health snapshots."});
                return rows;
            }

            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 7)
                    continue;
                aegis::InfoItem item;
                item.time = parts[1];
                item.tag = parts[2];
                item.source = parts[3];
                item.name = parts[4];
                item.value = parts[5];
                item.detail = parts[6];
                rows.push_back(item);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            std::reverse(rows.begin(), rows.end());
            if (rows.empty())
                rows.push_back({"No health history", "", "Waiting", "", "Provider health file is present but has no readable rows."});
            return rows;
        }

        void ClearNotificationJournal()
        {
            std::error_code ec;
            std::filesystem::remove(NotificationFile(), ec);
            seen_notification_keys_.clear();
            status_ = "Notification journal cleared.";
        }

        void ClearPredictionAudit()
        {
            std::error_code ec;
            std::filesystem::remove(PredictionAuditFile(), ec);
            status_ = "Prediction audit cleared.";
        }

        bool WriteLinesAtomic(const std::filesystem::path& path, const std::vector<std::string>& lines) const
        {
            std::error_code ec;
            std::filesystem::create_directories(path.parent_path(), ec);
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream file(temp_path, std::ios::trunc);
            if (!file)
                return false;
            for (const std::string& line : lines)
                file << line << '\n';
            file.close();
            return file && CommitTempFile(temp_path, path);
        }

        void ExportWorkspaceReport()
        {
            const std::filesystem::path path = aegis::AppDataDirectory() / "aegis-workspace-export.tsv";
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream file(temp_path, std::ios::trunc);
            if (!file)
            {
                export_status_ = "Could not write export report.";
                status_ = export_status_;
                return;
            }
            const std::vector<const aegis::Game*> report_games = ReportFilteredGames();
            const std::vector<const aegis::Prediction*> report_predictions = ReportFilteredPredictions();
            const std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> report_market_pairs = ReportFilteredMarketPairs(1000);
            int report_book_lines = 0;
            for (const aegis::Game* game : report_games)
            {
                if (game == nullptr)
                    continue;
                for (const aegis::BetLink& link : game->bet_links)
                {
                    if (link.available && LinkMatchesReportProvider(link) && LinkMatchesReportMarket(link))
                        ++report_book_lines;
                }
            }

            file << "section\tname\tvalue\tdetail\n";
            file << "summary\tversion\t" << TsvField(AppVersionLabel()) << "\tbuild metadata\n";
            file << "summary\tfilters\t" << TsvField(ReportFilterSummary()) << "\tcurrent report export scope\n";
            file << "summary\tdata_trust\t" << DataTrustScore() << "\t" << TsvField(DataTrustLabel()) << "\n";
            file << "summary\tlast_refresh\t" << TsvField(last_refresh_label_) << "\t" << TsvField(AgeLabel(SecondsSinceLastRefresh())) << "\n";
            file << "summary\tgames\t" << report_games.size() << "\tfiltered book lines " << report_book_lines << "\n";
            file << "summary\tpredictions\t" << report_predictions.size() << "\tfiltered model rows\n";
            file << "summary\ttracked_markets\t" << report_market_pairs.size() << "\tfiltered market history rows\n";
            file << "summary\texposure_today\t" << TsvField(FormatMoneyText(TodayPreviewExposure())) << "\tlimit " << TsvField(FormatMoneyText(config_.daily_exposure_limit)) << "\n";
            file << "summary\tfavorites\t" << CountFavoriteGames() << "\t" << TsvField(config_.favorite_teams + " / " + config_.favorite_leagues) << "\n";
            file << "summary\tscenario_journal\t" << ReadScenarioJournalRows(1000).size() << "\tlocal decision snapshots\n";
            for (const aegis::InfoItem& row : MarketSnapshotSummaryRows())
            {
                file << "market_summary\t" << TsvField(row.name) << '\t'
                     << TsvField(row.value) << '\t'
                     << TsvField(row.detail) << "\n";
            }
            for (const aegis::InfoItem& row : DataAdapterRows())
                file << "data_adapter\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : aegis::OptionalFeedSchemaRows())
                file << "adapter_schema\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : RefreshTelemetryRows())
                file << "refresh_telemetry\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : RefreshChangeRows())
                file << "refresh_change\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : ProviderRefreshRows())
                file << "source_refresh\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : ProviderHealthHistoryRows(50))
                file << "provider_health\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : BacktestRowsByMarket())
                file << "backtest\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : ProviderSportQualityRows())
                file << "provider_sport_quality\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            for (const aegis::InfoItem& row : state_.diagnostics)
                file << "odds_diagnostic\t" << TsvField(FirstNonEmpty(row.name, row.label, "Diagnostic")) << '\t' << TsvField(FirstNonEmpty(row.value, row.status, row.state)) << '\t' << TsvField(FirstNonEmpty(row.detail, row.env, row.source)) << "\n";
            for (const aegis::InfoItem& row : state_.provider_sports)
                file << "provider_sport_status\t" << TsvField(FirstNonEmpty(row.name, row.label, "Sport")) << '\t' << TsvField(FirstNonEmpty(row.value, row.weight, row.status, row.state)) << '\t' << TsvField(FirstNonEmpty(row.detail, row.latency, row.source)) << "\n";
            if (config_.bankroll_analytics_enabled)
            {
                for (const aegis::InfoItem& row : BankrollRows())
                    file << "bankroll\t" << TsvField(row.name) << '\t' << TsvField(row.value) << '\t' << TsvField(row.detail) << "\n";
            }
            for (const aegis::Prediction* prediction_ptr : report_predictions)
            {
                const aegis::Prediction& prediction = *prediction_ptr;
                file << "prediction\t" << TsvField(prediction.matchup) << '\t'
                     << TsvField(prediction.confidence) << '\t'
                     << TsvField(prediction.pick + " / " + prediction.market + " / " + prediction.edge + " / " + prediction.data_trust + " / " + prediction.source_timestamp) << "\n";
            }
            for (const aegis::InfoItem& alert : state_.alerts)
            {
                file << "alert\t" << TsvField(FirstNonEmpty(alert.name, alert.label, "Alert")) << '\t'
                     << TsvField(FirstNonEmpty(alert.value, alert.tag, alert.time)) << '\t'
                     << TsvField(alert.detail) << "\n";
            }
            for (const aegis::InfoItem& notification : ReadNotificationItems(20))
            {
                file << "notification\t" << TsvField(FirstNonEmpty(notification.name, notification.label, "Notification")) << '\t'
                     << TsvField(FirstNonEmpty(notification.value, notification.tag, notification.time)) << '\t'
                     << TsvField(notification.detail) << "\n";
            }
            for (const DecisionJournalRow& row : ReadScenarioJournalRows(200))
            {
                const JournalLineComparison comparison = JournalCurrentComparison(row);
                const std::string current = comparison.found
                    ? comparison.provider + " / " + comparison.line + " / " + comparison.price + " / " + comparison.scope
                    : "no current comparable line";
                const std::string clv = comparison.found && comparison.saved_priced
                    ? FormatSignedMoneyText(comparison.clv_delta) + " " + ClvReadLabel(comparison)
                    : ClvReadLabel(comparison);
                file << "scenario_journal\t" << TsvField(row.date + " " + row.time + " / " + row.matchup) << '\t'
                     << TsvField(std::to_string(row.probability) + "% / " + FormatMoneyText(row.stake) + " / " + FormatSignedMoneyText(row.expected_return) + " / CLV " + clv) << '\t'
                     << TsvField(row.pick + " / saved " + row.provider + " / " + row.line + " / " + row.price + " / current " + current) << "\n";
            }
            int market_rows = 0;
            for (const auto& pair : report_market_pairs)
            {
                if (market_rows++ >= 50)
                    break;
                file << "market_history\t" << TsvField(pair.second.matchup + " / " + pair.second.book) << '\t'
                     << TsvField(pair.first.price + " -> " + pair.second.price) << '\t'
                     << TsvField(pair.first.line + " -> " + pair.second.line + " / " + SnapshotMoveLabel(pair.first, pair.second)) << "\n";
            }
            for (const std::string& id : watchlist_ids_)
            {
                const aegis::Game* game = GameById(id);
                if (game != nullptr && !ReportGamePassesFilters(*game))
                    continue;
                file << "watchlist\t" << TsvField(id) << "\tactive\t" << TsvField(WatchNote(id).empty() ? "Pinned ticket preview" : WatchNote(id)) << "\n";
            }

            file.close();
            if (!file || !CommitTempFile(temp_path, path))
            {
                export_status_ = "Could not finalize export report.";
                status_ = export_status_;
                return;
            }
            export_status_ = "Exported " + path.string();
            status_ = "Workspace report exported.";
            aegis::AppendDiagnosticLine("workspace export written: " + path.string());
        }

        std::string CsvField(std::string value) const
        {
            for (char& c : value)
            {
                if (c == '\r' || c == '\n')
                    c = ' ';
            }
            bool quote = value.find(',') != std::string::npos || value.find('"') != std::string::npos;
            std::string out;
            for (const char c : value)
            {
                if (c == '"')
                {
                    quote = true;
                    out += "\"\"";
                }
                else
                {
                    out.push_back(c);
                }
            }
            return quote ? "\"" + out + "\"" : out;
        }

        void ExportProviderHealthReport()
        {
            const std::filesystem::path path = aegis::AppDataDirectory() / "aegis-provider-health.csv";
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream file(temp_path, std::ios::trunc);
            if (!file)
            {
                export_status_ = "Could not write provider health report.";
                status_ = export_status_;
                return;
            }

            file << "section,name,value,detail\n";
            file << "summary,version," << CsvField(AppVersionLabel()) << ",build metadata\n";
            auto write_item = [&](const std::string& section, const aegis::InfoItem& item) {
                file << CsvField(section) << ','
                     << CsvField(FirstNonEmpty(item.name, item.label, item.book, "Source")) << ','
                     << CsvField(FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds)) << ','
                     << CsvField(FirstNonEmpty(item.detail, item.latency, item.env, item.source)) << "\n";
            };

            for (const aegis::InfoItem& item : ProviderHealthRows()) write_item("provider", item);
            for (const aegis::InfoItem& item : DataTrustRows()) write_item("trust", item);
            for (const aegis::InfoItem& item : DataAdapterRows()) write_item("adapter", item);
            for (const aegis::InfoItem& item : aegis::OptionalFeedSchemaRows()) write_item("adapter_schema", item);
            for (const aegis::InfoItem& item : RefreshTelemetryRows()) write_item("refresh", item);
            for (const aegis::InfoItem& item : RefreshChangeRows()) write_item("refresh_change", item);
            for (const aegis::InfoItem& item : ProviderRefreshRows()) write_item("source_refresh", item);
            for (const aegis::InfoItem& item : state_.diagnostics) write_item("odds_diagnostics", item);
            for (const aegis::InfoItem& item : state_.provider_sports) write_item("provider_sport_status", item);
            for (const aegis::InfoItem& item : ProviderHealthHistoryRows(200)) write_item("history", item);

            file.close();
            if (!file || !CommitTempFile(temp_path, path))
            {
                export_status_ = "Could not finalize provider health report.";
                status_ = export_status_;
                return;
            }
            export_status_ = "Exported " + path.string();
            status_ = "Provider health report exported.";
            aegis::AppendDiagnosticLine("provider health export written: " + path.string());
        }

        void ExportDiagnosticBundle()
        {
            const std::filesystem::path dir = DiagnosticBundleDirectory();
            std::error_code ec;
            std::filesystem::create_directories(dir, ec);
            if (ec)
            {
                export_status_ = "Could not create diagnostic bundle folder.";
                status_ = export_status_;
                return;
            }

            std::vector<std::string> summary = {
                "Aegis Sports Betting AI Diagnostic Bundle",
                "Version: " + AppVersionLabel(),
                "Generated: " + TodayDateLabel() + " " + aegis::NowTimeLabel(),
                "Source badge: " + state_.source_badge,
                "Data trust: " + DataTrustLabel(),
                "Last refresh: " + last_refresh_label_ + " / " + AgeLabel(SecondsSinceLastRefresh()),
                "Events: " + std::to_string(static_cast<int>(state_.games.size())),
                "Predictions: " + std::to_string(static_cast<int>(state_.predictions.size())),
                "Odds matched games: " + std::to_string(CountOddsMatchedGames()),
                "Odds issue games: " + std::to_string(CountOddsIssueGames()),
                "Secrets: not included"
            };

            std::vector<std::string> startup = {"name\tvalue\tdetail"};
            for (const aegis::InfoItem& row : startup_integrity_)
                startup.push_back(TsvField(row.name) + "\t" + TsvField(row.value) + "\t" + TsvField(row.detail));

            std::vector<std::string> health = {"section\tname\tvalue\tdetail"};
            auto add_rows = [&](const std::string& section, const std::vector<aegis::InfoItem>& rows) {
                for (const aegis::InfoItem& row : rows)
                    health.push_back(TsvField(section) + "\t" + TsvField(FirstNonEmpty(row.name, row.label, "Signal")) + "\t" + TsvField(FirstNonEmpty(row.value, row.weight, row.state, row.status, row.line, row.odds)) + "\t" + TsvField(FirstNonEmpty(row.detail, row.latency, row.env, row.source)));
            };
            add_rows("provider", ProviderHealthRows());
            add_rows("trust", DataTrustRows());
            add_rows("adapters", DataAdapterRows());
            add_rows("refresh", RefreshTelemetryRows());
            add_rows("refresh_change", RefreshChangeRows());
            add_rows("source_refresh", ProviderRefreshRows());
            add_rows("odds_diagnostics", state_.diagnostics);
            add_rows("provider_sport_status", state_.provider_sports);

            std::vector<std::string> settings = {
                "[redacted]",
                "auth_base_url=" + config_.auth_base_url,
                "config_schema_version=" + std::to_string(config_.config_schema_version),
                "odds_api_key=" + std::string(config_.odds_api_key.empty() ? "empty" : "encrypted-local"),
                "kalshi_key_id=" + std::string(config_.kalshi_key_id.empty() ? "empty" : "encrypted-local"),
                "kalshi_private_key=" + std::string(config_.kalshi_private_key.empty() ? "empty" : "encrypted-local"),
                "injury_feed_url=" + config_.injury_feed_url,
                "lineup_feed_url=" + config_.lineup_feed_url,
                "news_feed_url=" + config_.news_feed_url,
                "props_feed_url=" + config_.props_feed_url,
                "paper_only_mode=" + std::string(config_.paper_only_mode ? "true" : "false"),
                "require_live_confirmation=" + std::string(config_.require_live_confirmation ? "true" : "false"),
                "responsible_use_accepted=" + std::string(config_.responsible_use_accepted ? "true" : "false"),
                "legal_location_confirmed=" + std::string(config_.legal_location_confirmed ? "true" : "false")
            };

            const bool ok =
                WriteLinesAtomic(dir / "summary.txt", summary) &&
                WriteLinesAtomic(dir / "startup-integrity.tsv", startup) &&
                WriteLinesAtomic(dir / "source-health.tsv", health) &&
                WriteLinesAtomic(dir / "settings-redacted.ini", settings);
            if (!ok)
            {
                export_status_ = "Could not write diagnostic bundle.";
                status_ = export_status_;
                return;
            }

            export_status_ = "Exported safe diagnostic bundle to " + dir.string();
            status_ = "Safe diagnostic bundle exported without secrets.";
            aegis::AppendDiagnosticLine("safe diagnostic bundle exported: " + dir.string());
        }

        void ExportCsvReport()
        {
            const std::filesystem::path path = aegis::AppDataDirectory() / "aegis-report.csv";
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream file(temp_path, std::ios::trunc);
            if (!file)
            {
                export_status_ = "Could not write CSV report.";
                status_ = export_status_;
                return;
            }
            const std::vector<const aegis::Game*> report_games = ReportFilteredGames();
            const std::vector<const aegis::Prediction*> report_predictions = ReportFilteredPredictions();
            const std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> report_market_pairs = ReportFilteredMarketPairs(1000);
            file << "section,name,value,detail\n";
            file << "summary,version," << CsvField(AppVersionLabel()) << ",build metadata\n";
            file << "summary,filters," << CsvField(ReportFilterSummary()) << ",current report export scope\n";
            file << "summary,games," << report_games.size() << ",filtered games\n";
            file << "summary,predictions," << report_predictions.size() << ",filtered model rows\n";
            file << "summary,tracked_markets," << report_market_pairs.size() << ",filtered market history rows\n";
            auto write_item = [&](const std::string& section, const aegis::InfoItem& item) {
                file << CsvField(section) << ','
                     << CsvField(FirstNonEmpty(item.name, item.label, item.book, "Signal")) << ','
                     << CsvField(FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds)) << ','
                     << CsvField(FirstNonEmpty(item.detail, item.latency, item.env, item.source)) << "\n";
            };
            for (const aegis::InfoItem& item : DailyReportRows()) write_item("daily", item);
            for (const aegis::InfoItem& item : DataAdapterRows()) write_item("adapters", item);
            for (const aegis::InfoItem& item : aegis::OptionalFeedSchemaRows()) write_item("adapter_schema", item);
            for (const aegis::InfoItem& item : RefreshTelemetryRows()) write_item("refresh", item);
            for (const aegis::InfoItem& item : RefreshChangeRows()) write_item("refresh_change", item);
            for (const aegis::InfoItem& item : ProviderRefreshRows()) write_item("source_refresh", item);
            for (const aegis::InfoItem& item : ProviderHealthHistoryRows(200)) write_item("provider_health", item);
            for (const aegis::InfoItem& item : BacktestRowsByMarket()) write_item("backtest", item);
            for (const aegis::InfoItem& item : ProviderSportQualityRows()) write_item("provider_sport_quality", item);
            for (const aegis::InfoItem& item : state_.diagnostics) write_item("odds_diagnostics", item);
            for (const aegis::InfoItem& item : state_.provider_sports) write_item("provider_sport_status", item);
            for (const aegis::InfoItem& item : ScenarioClvSummaryRows()) write_item("scenario_clv", item);
            if (config_.bankroll_analytics_enabled)
                for (const aegis::InfoItem& item : BankrollRows()) write_item("bankroll", item);
            for (const aegis::Prediction* prediction_ptr : report_predictions)
            {
                const aegis::Prediction& prediction = *prediction_ptr;
                file << "prediction,"
                     << CsvField(prediction.matchup) << ','
                     << CsvField(prediction.confidence + " / " + prediction.confidence_band) << ','
                     << CsvField(prediction.pick + " / " + prediction.market + " / " + prediction.edge + " / " + prediction.data_trust + " / " + prediction.source_timestamp) << "\n";
            }
            int market_rows = 0;
            for (const auto& pair : report_market_pairs)
            {
                if (market_rows++ >= 100)
                    break;
                file << "market_history,"
                     << CsvField(pair.second.matchup + " / " + pair.second.book) << ','
                     << CsvField(pair.first.price + " -> " + pair.second.price) << ','
                     << CsvField(pair.first.line + " -> " + pair.second.line + " / " + SnapshotMoveLabel(pair.first, pair.second)) << "\n";
            }
            for (const DecisionJournalRow& row : ReadScenarioJournalRows(300))
            {
                const aegis::Game* game = GameById(row.game_id);
                if (game != nullptr && !ReportGamePassesFilters(*game))
                    continue;
                if (game == nullptr && !ReportLeagueMatches(row.matchup + " " + row.market + " " + row.provider))
                    continue;
                const JournalLineComparison comparison = JournalCurrentComparison(row);
                file << "scenario_journal,"
                     << CsvField(row.date + " " + row.time + " " + row.matchup) << ','
                     << CsvField(row.pick + " " + row.price) << ','
                     << CsvField("EV " + FormatSignedMoneyText(row.expected_return) + " CLV " + (comparison.found && comparison.saved_priced ? FormatSignedMoneyText(comparison.clv_delta) : ClvReadLabel(comparison))) << "\n";
            }
            file.close();
            if (!file || !CommitTempFile(temp_path, path))
            {
                export_status_ = "Could not finalize CSV report.";
                status_ = export_status_;
                return;
            }
            export_status_ = "Exported " + path.string();
            status_ = "CSV report exported.";
            aegis::AppendDiagnosticLine("csv report written: " + path.string());
        }

        std::string PdfEscape(const std::string& value) const
        {
            std::string out;
            for (const char c : value)
            {
                if (c == '(' || c == ')' || c == '\\')
                    out.push_back('\\');
                if (static_cast<unsigned char>(c) >= 32 && static_cast<unsigned char>(c) <= 126)
                    out.push_back(c);
                else if (c == '\t')
                    out.push_back(' ');
            }
            return out;
        }

        bool WriteSimplePdf(const std::filesystem::path& path, const std::vector<std::string>& lines) const
        {
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ostringstream content;
            content << "BT\n/F1 10 Tf\n50 760 Td\n14 TL\n";
            int rendered = 0;
            for (const std::string& line : lines)
            {
                if (rendered++ >= 50)
                    break;
                content << "(" << PdfEscape(line).substr(0, 110) << ") Tj\nT*\n";
            }
            content << "ET\n";
            const std::string stream = content.str();

            std::vector<std::string> objects;
            objects.push_back("<< /Type /Catalog /Pages 2 0 R >>");
            objects.push_back("<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
            objects.push_back("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>");
            objects.push_back("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
            objects.push_back("<< /Length " + std::to_string(stream.size()) + " >>\nstream\n" + stream + "endstream");

            std::ofstream file(temp_path, std::ios::binary | std::ios::trunc);
            if (!file)
                return false;
            std::vector<std::streamoff> offsets;
            file << "%PDF-1.4\n";
            for (size_t i = 0; i < objects.size(); ++i)
            {
                offsets.push_back(file.tellp());
                file << (i + 1) << " 0 obj\n" << objects[i] << "\nendobj\n";
            }
            const std::streamoff xref = file.tellp();
            file << "xref\n0 " << (objects.size() + 1) << "\n";
            file << "0000000000 65535 f \n";
            for (const std::streamoff offset : offsets)
                file << std::setw(10) << std::setfill('0') << static_cast<long long>(offset) << " 00000 n \n";
            file << std::setfill(' ');
            file << "trailer\n<< /Size " << (objects.size() + 1) << " /Root 1 0 R >>\nstartxref\n" << static_cast<long long>(xref) << "\n%%EOF\n";
            file.close();
            return file && CommitTempFile(temp_path, path);
        }

        void ExportPdfReport()
        {
            const std::filesystem::path path = aegis::AppDataDirectory() / "aegis-report.pdf";
            const std::vector<const aegis::Game*> report_games = ReportFilteredGames();
            const std::vector<const aegis::Prediction*> report_predictions = ReportFilteredPredictions();
            const std::vector<std::pair<MarketSnapshotRow, MarketSnapshotRow>> report_market_pairs = ReportFilteredMarketPairs(1000);
            std::vector<std::string> lines;
            lines.push_back("Aegis Sports Betting AI Report");
            lines.push_back("Version " + AppVersionLabel());
            lines.push_back("Generated " + TodayDateLabel() + " " + aegis::NowTimeLabel());
            lines.push_back("Filters: " + ReportFilterSummary());
            lines.push_back("Source: " + state_.source_badge + " / Trust " + std::to_string(DataTrustScore()) + "%");
            lines.push_back("Games: " + std::to_string(static_cast<int>(report_games.size())) + " / Predictions: " + std::to_string(static_cast<int>(report_predictions.size())) + " / Markets: " + std::to_string(static_cast<int>(report_market_pairs.size())));
            lines.push_back("Exposure today: " + FormatMoneyText(TodayPreviewExposure()) + " / Limit " + FormatMoneyText(config_.daily_exposure_limit));
            int rendered_predictions = 0;
            for (const aegis::Prediction* prediction : report_predictions)
            {
                if (rendered_predictions++ >= 12)
                    break;
                lines.push_back("Pick - " + prediction->matchup + ": " + prediction->pick + " / " + prediction->market + " / " + prediction->confidence + " / " + prediction->data_trust);
            }
            int rendered_markets = 0;
            for (const auto& pair : report_market_pairs)
            {
                if (rendered_markets++ >= 8)
                    break;
                lines.push_back("Market - " + pair.second.matchup + " / " + pair.second.book + ": " + pair.first.price + " -> " + pair.second.price + " / " + SnapshotMoveLabel(pair.first, pair.second));
            }
            for (const aegis::InfoItem& item : DailyReportRows())
                lines.push_back(FirstNonEmpty(item.name, item.label, "Daily") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds) + " - " + item.detail);
            for (const aegis::InfoItem& item : ScenarioClvSummaryRows())
                lines.push_back("Scenario CLV - " + FirstNonEmpty(item.name, item.label, "Signal") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds));
            for (const aegis::InfoItem& item : DataAdapterRows())
                lines.push_back("Adapter - " + FirstNonEmpty(item.name, item.label, "Adapter") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds));
            for (const aegis::InfoItem& item : aegis::OptionalFeedSchemaRows())
                lines.push_back("Adapter schema - " + FirstNonEmpty(item.name, item.label, "Adapter") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds));
            for (const aegis::InfoItem& item : RefreshTelemetryRows())
                lines.push_back("Refresh - " + FirstNonEmpty(item.name, item.label, "Refresh") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds));
            for (const aegis::InfoItem& item : RefreshChangeRows())
                lines.push_back("Refresh change - " + FirstNonEmpty(item.name, item.label, "Change") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds) + " - " + item.detail);
            for (const aegis::InfoItem& item : ProviderRefreshRows())
                lines.push_back("Source refresh - " + FirstNonEmpty(item.name, item.label, "Source") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds) + " - " + item.detail);
            for (const aegis::InfoItem& item : state_.diagnostics)
                lines.push_back("Odds diagnostics - " + FirstNonEmpty(item.name, item.label, "Diagnostic") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds) + " - " + FirstNonEmpty(item.detail, item.env, item.source));
            for (const aegis::InfoItem& item : state_.provider_sports)
                lines.push_back("Per-sport odds - " + FirstNonEmpty(item.name, item.label, "Sport") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds) + " - " + FirstNonEmpty(item.detail, item.env, item.source));
            for (const aegis::InfoItem& item : ProviderHealthHistoryRows(12))
                lines.push_back("Health - " + FirstNonEmpty(item.name, item.label, "Source") + ": " + FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds));
            if (!WriteSimplePdf(path, lines))
            {
                export_status_ = "Could not write PDF report.";
                status_ = export_status_;
                return;
            }
            export_status_ = "Exported " + path.string();
            status_ = "PDF report exported.";
            aegis::AppendDiagnosticLine("pdf report written: " + path.string());
        }

        void SaveSourceSettings()
        {
            const std::vector<std::pair<std::string, std::string>> urls = {
                {"Injury Feed URL", aegis::Trim(injury_feed_url_)},
                {"Lineup Feed URL", aegis::Trim(lineup_feed_url_)},
                {"News Feed URL", aegis::Trim(news_feed_url_)},
                {"Player Props Feed URL", aegis::Trim(props_feed_url_)}
            };
            std::vector<std::string> validation_errors;
            for (const auto& row : urls)
            {
                if (!OptionalUrlIsValid(row.second))
                    validation_errors.push_back(row.first + " must start with http:// or https://.");
            }
            if (!validation_errors.empty())
            {
                status_ = "Settings not saved: " + validation_errors.front();
                return;
            }

            bool adjusted = false;
            settings_refresh_seconds_ = std::clamp(settings_refresh_seconds_, 5, 3600);
            settings_tracked_games_ = std::clamp(settings_tracked_games_, 12, 160);
            settings_model_count_ = std::clamp(settings_model_count_, 2, 32);
            settings_bankroll_starting_amount_ = std::clamp(settings_bankroll_starting_amount_, 1.0, 10000000.0);
            settings_max_ticket_amount_ = std::clamp(settings_max_ticket_amount_, 1.0, 100000.0);
            settings_daily_exposure_limit_ = std::clamp(settings_daily_exposure_limit_, 1.0, 1000000.0);
            settings_min_ticket_confidence_ = std::clamp(settings_min_ticket_confidence_, 1, 99);
            settings_alert_confidence_threshold_ = std::clamp(settings_alert_confidence_threshold_, 50, 99);
            if (settings_daily_exposure_limit_ < settings_max_ticket_amount_)
            {
                settings_daily_exposure_limit_ = settings_max_ticket_amount_;
                adjusted = true;
            }
            if (!settings_paper_only_mode_ && !settings_require_live_confirmation_)
            {
                settings_require_live_confirmation_ = true;
                adjusted = true;
            }
            config_.odds_api_key = aegis::Trim(odds_api_key_);
            config_.favorite_teams = aegis::Trim(favorite_teams_);
            config_.favorite_leagues = aegis::Trim(favorite_leagues_);
            config_.injury_feed_url = aegis::Trim(injury_feed_url_);
            config_.lineup_feed_url = aegis::Trim(lineup_feed_url_);
            config_.news_feed_url = aegis::Trim(news_feed_url_);
            config_.props_feed_url = aegis::Trim(props_feed_url_);
            config_.refresh_seconds = settings_refresh_seconds_;
            config_.tracked_games = settings_tracked_games_;
            config_.model_count = settings_model_count_;
            config_.bankroll_starting_amount = settings_bankroll_starting_amount_;
            config_.max_ticket_amount = settings_max_ticket_amount_;
            config_.daily_exposure_limit = settings_daily_exposure_limit_;
            config_.min_ticket_confidence = settings_min_ticket_confidence_;
            config_.paper_only_mode = settings_paper_only_mode_;
            config_.require_live_confirmation = settings_require_live_confirmation_;
            config_.notifications_enabled = settings_notifications_enabled_;
            config_.bankroll_analytics_enabled = settings_bankroll_analytics_enabled_;
            config_.player_props_enabled = settings_player_props_enabled_;
            config_.responsible_use_accepted = settings_responsible_use_accepted_;
            config_.legal_location_confirmed = settings_legal_location_confirmed_;
            config_.alert_confidence_threshold = settings_alert_confidence_threshold_;
            config_.alert_watchlist_only = settings_alert_watchlist_only_;
            config_.alert_line_movement_only = settings_alert_line_movement_only_;
            if (config_.paper_only_mode)
                live_order_preview_ = false;

            if (aegis::SaveConfig(config_))
            {
                std::snprintf(odds_api_key_, sizeof(odds_api_key_), "%s", config_.odds_api_key.c_str());
                std::snprintf(favorite_teams_, sizeof(favorite_teams_), "%s", config_.favorite_teams.c_str());
                std::snprintf(favorite_leagues_, sizeof(favorite_leagues_), "%s", config_.favorite_leagues.c_str());
                std::snprintf(injury_feed_url_, sizeof(injury_feed_url_), "%s", config_.injury_feed_url.c_str());
                std::snprintf(lineup_feed_url_, sizeof(lineup_feed_url_), "%s", config_.lineup_feed_url.c_str());
                std::snprintf(news_feed_url_, sizeof(news_feed_url_), "%s", config_.news_feed_url.c_str());
                std::snprintf(props_feed_url_, sizeof(props_feed_url_), "%s", config_.props_feed_url.c_str());
                odds_validation_ = {};
                adapter_probes_.clear();
                status_ = config_.odds_api_key.empty()
                    ? "Source settings saved. Odds API key cleared."
                    : "Source settings saved securely. Refreshing direct feeds now.";
                if (adjusted)
                    status_ += " Some values were adjusted to safe ranges.";
                startup_integrity_ = BuildStartupIntegrityRows();
                BeginSportsRefresh(false, "Source settings saved", "Rebuilding scoreboard, odds links, and model confidence from direct hosts.");
            }
            else
            {
                status_ = "Could not save source settings beside the desktop executable.";
            }
        }

        void ValidateOddsKey()
        {
            const std::string key = aegis::Trim(odds_api_key_[0] == '\0' ? config_.odds_api_key : std::string(odds_api_key_));
            odds_validation_ = aegis::ValidateOddsApiKey(key);
            if (odds_validation_.ok)
            {
                status_ = "Odds API key validated: " + std::to_string(odds_validation_.sports) + " sports returned.";
                if (config_.odds_api_key != key)
                {
                    config_.odds_api_key = key;
                    aegis::SaveConfig(config_);
                }
            }
            else
            {
                status_ = odds_validation_.status.empty() ? "Odds API key validation failed." : "Odds API key: " + odds_validation_.status + ".";
            }
        }

        AdapterProbe ProbeAdapterUrl(const std::string& key, const std::string& name, const std::string& url) const
        {
            const aegis::OptionalFeedValidationResult validation = ValidateOptionalFeedUrl(key, name, url);
            AdapterProbe probe;
            probe.checked = true;
            probe.ok = validation.ok;
            probe.reachable = validation.reachable;
            probe.status_code = validation.status_code;
            probe.records = validation.records;
            probe.errors = validation.errors;
            probe.warnings = validation.warnings;
            probe.contract = validation.contract;
            probe.status = validation.status.empty() ? (validation.ok ? "Schema valid" : "Schema invalid") : validation.status;
            probe.detail = validation.detail;
            return probe;
        }

        void ValidateDataAdapters()
        {
            struct AdapterTarget
            {
                std::string key;
                std::string name;
                std::string url;
            };

            const std::vector<AdapterTarget> targets = {
                {"injury", "Injury feed", aegis::Trim(injury_feed_url_[0] == '\0' ? config_.injury_feed_url : std::string(injury_feed_url_))},
                {"lineup", "Lineup feed", aegis::Trim(lineup_feed_url_[0] == '\0' ? config_.lineup_feed_url : std::string(lineup_feed_url_))},
                {"news", "News feed", aegis::Trim(news_feed_url_[0] == '\0' ? config_.news_feed_url : std::string(news_feed_url_))},
                {"props", "Player props feed", aegis::Trim(props_feed_url_[0] == '\0' ? config_.props_feed_url : std::string(props_feed_url_))}
            };

            adapter_probes_.clear();
            int configured = 0;
            int reachable = 0;
            int valid = 0;
            for (const AdapterTarget& target : targets)
            {
                AdapterProbe probe = ProbeAdapterUrl(target.key, target.name, target.url);
                adapter_probes_[target.key] = probe;
                if (!target.url.empty())
                    ++configured;
                if (probe.reachable)
                    ++reachable;
                if (probe.ok)
                    ++valid;
            }

            if (configured == 0)
            {
                status_ = "No optional data adapter URLs configured.";
                aegis::AppendDiagnosticLine("data adapter validation skipped: none configured");
                AppendProviderHealthSnapshot("validate_feeds");
                return;
            }

            status_ = "Data adapter validation finished: " + std::to_string(reachable) + "/" + std::to_string(configured) + " reachable, " + std::to_string(valid) + " schema-valid.";
            aegis::AppendDiagnosticLine("data adapter validation reachable=" + std::to_string(reachable) + " valid=" + std::to_string(valid) + " configured=" + std::to_string(configured));
            AppendProviderHealthSnapshot("validate_feeds");
        }

        void UpdateAdapterProbesFromRefresh(const std::vector<aegis::OptionalFeedValidationResult>& feeds)
        {
            for (const aegis::OptionalFeedValidationResult& feed : feeds)
            {
                AdapterProbe probe;
                probe.checked = true;
                probe.ok = feed.ok;
                probe.reachable = feed.reachable;
                probe.status_code = feed.status_code;
                probe.records = feed.records;
                probe.errors = feed.errors;
                probe.warnings = feed.warnings;
                probe.contract = feed.contract;
                probe.status = feed.status.empty() ? (feed.ok ? "Schema valid" : "Schema invalid") : feed.status;
                probe.detail = feed.detail;
                adapter_probes_[feed.feed_key] = probe;
            }
        }

        void ClearOddsKey()
        {
            odds_api_key_[0] = '\0';
            config_.odds_api_key.clear();
            odds_validation_ = {};
            aegis::SaveConfig(config_);
            status_ = "Odds API key cleared from encrypted storage.";
            BeginSportsRefresh(false, "Odds key cleared", "Rebuilding the board with scoreboard-only market coverage.");
        }

        void SaveKalshiSettings()
        {
            config_.kalshi_key_id = aegis::Trim(kalshi_key_id_);
            config_.kalshi_private_key = aegis::Trim(kalshi_private_key_);
            if (aegis::SaveConfig(config_))
            {
                kalshi_status_ = config_.kalshi_key_id.empty() && config_.kalshi_private_key.empty() ? "Empty" : "Saved";
                kalshi_detail_ = config_.kalshi_key_id.empty() && config_.kalshi_private_key.empty()
                    ? "No Kalshi credentials are saved."
                    : "Kalshi credentials saved in encrypted local storage. Real-money auto execution remains disabled.";
                status_ = "Kalshi credentials saved securely for read-only/preflight flows.";
            }
            else
            {
                status_ = "Could not save Kalshi credentials.";
            }
        }

        void ValidateKalshiFormat()
        {
            const std::string key_id = aegis::Trim(kalshi_key_id_);
            const std::string private_key = aegis::Trim(kalshi_private_key_);
            if (key_id.empty() || private_key.empty())
            {
                kalshi_status_ = "Incomplete";
                kalshi_detail_ = "Kalshi API access normally requires a key id and an RSA private key.";
                status_ = "Kalshi credentials are incomplete.";
                return;
            }
            if (private_key.find("PRIVATE KEY") == std::string::npos)
            {
                kalshi_status_ = "Check key";
                kalshi_detail_ = "Private key text should include a PRIVATE KEY header. Aegis will not execute orders.";
                status_ = "Kalshi private key format needs review.";
                return;
            }
            kalshi_status_ = "Format ready";
            kalshi_detail_ = "Credential format looks usable for future signed read/preflight calls. Automatic order placement is disabled.";
            status_ = "Kalshi credential format check passed.";
        }

        void ClearKalshiCredentials()
        {
            kalshi_key_id_[0] = '\0';
            kalshi_private_key_[0] = '\0';
            config_.kalshi_key_id.clear();
            config_.kalshi_private_key.clear();
            kalshi_status_ = "Cleared";
            kalshi_detail_ = "Kalshi encrypted credentials were removed.";
            aegis::SaveConfig(config_);
            status_ = "Kalshi credentials cleared.";
        }

        std::filesystem::path WatchlistFile() const
        {
            return aegis::AppDataDirectory() / "watchlist.tsv";
        }

        std::filesystem::path SlipAuditFile() const
        {
            return aegis::AppDataDirectory() / "slip-audit.tsv";
        }

        std::filesystem::path ExposureLedgerFile() const
        {
            return aegis::AppDataDirectory() / "exposure-ledger.tsv";
        }

        std::string TodayDateLabel() const
        {
            const auto now = std::chrono::system_clock::now();
            const std::time_t tt = std::chrono::system_clock::to_time_t(now);
            std::tm local{};
            localtime_s(&local, &tt);
            std::ostringstream stream;
            stream << std::put_time(&local, "%Y-%m-%d");
            return stream.str();
        }

        double TodayPreviewExposure() const
        {
            std::ifstream file(ExposureLedgerFile());
            if (!file)
                return 0.0;
            const std::string today = TodayDateLabel();
            double total = 0.0;
            std::string line;
            while (std::getline(file, line))
            {
                std::stringstream stream(line);
                std::string date;
                std::string mode;
                std::string amount;
                std::getline(stream, date, '\t');
                std::getline(stream, mode, '\t');
                std::getline(stream, amount, '\t');
                if (date == today)
                    total += std::max(0.0, std::atof(amount.c_str()));
            }
            return total;
        }

        std::vector<ExposureRow> ReadExposureRows(int max_rows = 400) const
        {
            std::ifstream file(ExposureLedgerFile());
            std::vector<ExposureRow> rows;
            if (!file)
                return rows;
            std::string line;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                if (parts.size() < 5)
                    continue;
                ExposureRow row;
                row.date = parts[0];
                row.mode = parts[1];
                row.amount = std::max(0.0, std::atof(parts[2].c_str()));
                row.game_id = parts[3];
                row.matchup = parts[4];
                rows.push_back(row);
                if (rows.size() > static_cast<size_t>(max_rows))
                    rows.erase(rows.begin());
            }
            return rows;
        }

        void AppendExposureLedger(const aegis::Prediction& prediction, const std::string& mode)
        {
            std::ofstream file(ExposureLedgerFile(), std::ios::app);
            if (!file)
                return;
            file << TodayDateLabel() << '\t'
                 << mode << '\t'
                 << preview_amount_ << '\t'
                 << prediction.game_id << '\t'
                 << TsvField(prediction.matchup) << '\n';
            file.close();
            PruneLocalTsvFile(ExposureLedgerFile(), 2000);
        }

        void ClearExposureLedger()
        {
            std::error_code ec;
            std::filesystem::remove(ExposureLedgerFile(), ec);
            status_ = "Exposure ledger reset.";
        }

        void LoadWatchlist()
        {
            watchlist_ids_.clear();
            watch_notes_.clear();
            std::ifstream file(WatchlistFile());
            if (!file)
                return;
            std::string line;
            std::set<std::string> seen;
            while (std::getline(file, line))
            {
                const std::vector<std::string> parts = SplitTabsLocal(line);
                const std::string id = aegis::Trim(parts.empty() ? line : parts[0]);
                if (!id.empty() && seen.insert(id).second)
                {
                    watchlist_ids_.push_back(id);
                    if (parts.size() > 1)
                        watch_notes_[id] = aegis::Trim(parts[1]);
                }
            }
        }

        void SaveWatchlist() const
        {
            const std::filesystem::path path = WatchlistFile();
            const std::filesystem::path temp_path = TempOutputPath(path);
            std::ofstream file(temp_path, std::ios::trunc);
            if (!file)
                return;
            for (const std::string& id : watchlist_ids_)
            {
                const auto note = watch_notes_.find(id);
                file << TsvField(id) << '\t' << TsvField(note == watch_notes_.end() ? "" : note->second) << "\n";
            }
            file.close();
            CommitTempFile(temp_path, path);
        }

        void ClearWatchlist()
        {
            watchlist_ids_.clear();
            watch_notes_.clear();
            SaveWatchlist();
            status_ = "Watchlist cleared.";
        }

        bool IsWatched(const std::string& id) const
        {
            return std::find(watchlist_ids_.begin(), watchlist_ids_.end(), id) != watchlist_ids_.end();
        }

        void AddWatch(const std::string& id)
        {
            if (id.empty() || IsWatched(id))
                return;
            watchlist_ids_.push_back(id);
            if (watch_notes_.find(id) == watch_notes_.end())
                watch_notes_[id] = "Watching line movement and confidence changes.";
            SaveWatchlist();
            status_ = "Added market to watchlist.";
        }

        void RemoveWatch(const std::string& id)
        {
            watchlist_ids_.erase(std::remove(watchlist_ids_.begin(), watchlist_ids_.end(), id), watchlist_ids_.end());
            watch_notes_.erase(id);
            SaveWatchlist();
            status_ = "Removed market from watchlist.";
        }

        std::string WatchNote(const std::string& id) const
        {
            const auto it = watch_notes_.find(id);
            return it == watch_notes_.end() ? "" : it->second;
        }

        void SetWatchNote(const std::string& id, const std::string& note)
        {
            if (id.empty())
                return;
            watch_notes_[id] = note;
            SaveWatchlist();
        }

        void AddFavoriteTeam(const std::string& team)
        {
            const std::string clean = aegis::Trim(team);
            if (clean.empty())
                return;
            std::vector<std::string> teams = CsvTokens(config_.favorite_teams);
            const std::string lower = aegis::Lower(clean);
            if (std::find(teams.begin(), teams.end(), lower) != teams.end())
            {
                status_ = clean + " is already on the focus board.";
                return;
            }
            config_.favorite_teams = aegis::Trim(config_.favorite_teams);
            if (!config_.favorite_teams.empty())
                config_.favorite_teams += ", ";
            config_.favorite_teams += clean;
            std::snprintf(favorite_teams_, sizeof(favorite_teams_), "%s", config_.favorite_teams.c_str());
            aegis::SaveConfig(config_);
            status_ = "Added " + clean + " to favorite teams.";
        }

        const aegis::Game* GameById(const std::string& id) const
        {
            for (const aegis::Game& game : state_.games)
            {
                if (game.id == id)
                    return &game;
            }
            return nullptr;
        }

        const aegis::Prediction* PredictionByGameId(const std::string& id) const
        {
            for (const aegis::Prediction& prediction : state_.predictions)
            {
                if (prediction.game_id == id)
                    return &prediction;
            }
            return nullptr;
        }

        std::string ActionLabel(const aegis::Prediction& prediction) const
        {
            if (prediction.status_key == "final")
                return "No action: final game retained for audit.";
            if (prediction.odds == "--" || prediction.odds == "Feed snapshot")
                return "Monitor: needs direct sportsbook price.";
            if (prediction.confidence_value < config_.min_ticket_confidence)
                return "Blocked: below configured confidence floor.";
            if (preview_amount_ > config_.max_ticket_amount)
                return "Blocked: preview amount is above local limit.";
            if (!live_order_preview_)
                return "Ready: paper ticket logs locally.";
            if (config_.paper_only_mode)
                return "Paper only: live provider handoff disabled in settings.";
            if (!config_.responsible_use_accepted || !config_.legal_location_confirmed)
                return "Locked: complete safety acknowledgements in Settings.";
            if (config_.require_live_confirmation && !live_acknowledged_)
                return "Locked: confirm manual provider handoff first.";
            return "Preview only: open provider and confirm manually.";
        }

        std::string OrderPreviewLine(const aegis::Prediction& prediction) const
        {
            std::ostringstream stream;
            stream << (live_order_preview_ ? "Live preview" : "Paper ticket")
                   << " / " << prediction.market
                   << " / " << prediction.odds
                   << " / $" << std::fixed << std::setprecision(2) << preview_amount_;
            return stream.str();
        }

        void AppendSlipAudit(const aegis::Prediction& prediction, const std::string& mode, const std::string& result)
        {
            std::ofstream file(SlipAuditFile(), std::ios::app);
            if (!file)
                return;
            file << aegis::NowTimeLabel() << '\t'
                 << mode << '\t'
                 << prediction.game_id << '\t'
                 << prediction.matchup << '\t'
                 << prediction.market << '\t'
                 << prediction.pick << '\t'
                 << prediction.odds << '\t'
                 << preview_amount_ << '\t'
                 << result << '\n';
            file.close();
            PruneLocalTsvFile(SlipAuditFile(), 2000);
        }

        void SubmitSlipPreview(const aegis::Prediction& prediction)
        {
            if (prediction.status_key == "final")
            {
                AppendSlipAudit(prediction, live_order_preview_ ? "live-preview" : "paper", "blocked_final_game");
                status_ = "Final games are audit-only and cannot be staged as tickets.";
                return;
            }
            if (preview_amount_ > config_.max_ticket_amount)
            {
                AppendSlipAudit(prediction, live_order_preview_ ? "live-preview" : "paper", "blocked_ticket_limit");
                status_ = "Preview amount is above the configured ticket limit.";
                return;
            }
            if (TodayPreviewExposure() + preview_amount_ > config_.daily_exposure_limit)
            {
                AppendSlipAudit(prediction, live_order_preview_ ? "live-preview" : "paper", "blocked_daily_exposure_limit");
                status_ = "Daily preview exposure limit reached.";
                return;
            }
            if (prediction.confidence_value < config_.min_ticket_confidence)
            {
                AppendSlipAudit(prediction, live_order_preview_ ? "live-preview" : "paper", "blocked_confidence_floor");
                status_ = "Prediction is below the configured confidence floor.";
                return;
            }
            if (!live_order_preview_)
            {
                AppendSlipAudit(prediction, "paper", "paper_submitted");
                AppendExposureLedger(prediction, "paper");
                status_ = "Paper ticket logged locally for " + prediction.matchup + ".";
                return;
            }

            if (config_.paper_only_mode)
            {
                status_ = "Paper-only mode is enabled. Disable it in Settings before opening provider handoff.";
                AppendSlipAudit(prediction, "live-preview", "blocked_paper_only_mode");
                return;
            }
            if (!config_.responsible_use_accepted || !config_.legal_location_confirmed)
            {
                status_ = "Manual provider handoff is locked until responsible-use and legal/location acknowledgements are saved.";
                AppendSlipAudit(prediction, "live-preview", "blocked_missing_disclosures");
                return;
            }
            if (config_.require_live_confirmation && !live_acknowledged_)
            {
                status_ = "Live preview requires the confirmation checkbox first.";
                AppendSlipAudit(prediction, "live-preview", "blocked_missing_confirmation");
                return;
            }
            if (aegis::Trim(config_.kalshi_key_id).empty())
            {
                status_ = "Kalshi credentials are not saved. Live preview can only open public provider links.";
                AppendSlipAudit(prediction, "live-preview", "blocked_missing_kalshi_credentials");
            }
            else
            {
                status_ = "Live order handoff only: opening provider for manual confirmation.";
                AppendSlipAudit(prediction, "live-preview", "manual_provider_handoff");
                AppendExposureLedger(prediction, "manual_handoff");
            }

            const aegis::Game* game = GameById(prediction.game_id);
            if (game != nullptr)
            {
                auto kalshi = std::find_if(game->bet_links.begin(), game->bet_links.end(), [](const aegis::BetLink& link) {
                    return aegis::Lower(link.provider_key + " " + link.title).find("kalshi") != std::string::npos;
                });
                if (kalshi != game->bet_links.end() && !kalshi->url.empty())
                    aegis::OpenExternalUrl(kalshi->url);
            }
        }

        bool ProviderMatches(const aegis::BetLink& link, const aegis::Game& game) const
        {
            const std::string filter = aegis::Lower(provider_filter_);
            if (filter == "all")
                return true;
            const std::string key = aegis::Lower(link.provider_key + " " + link.title + " " + link.kind);
            if (filter == "sportsbook")
                return key.find("sportsbook") != std::string::npos;
            if (filter == "kalshi")
                return key.find("kalshi") != std::string::npos || key.find("exchange") != std::string::npos;
            if (filter == "live")
                return game.status_key == "live" && link.available;
            return true;
        }

        void RenderProviderFilterButton(const char* label, const char* filter, IconKind icon)
        {
            if (AegisIconButton(label, icon, ImVec2(132.0f, 36.0f), provider_filter_ == filter))
                provider_filter_ = filter;
        }

        void RenderMarketAccessBoard()
        {
            ImGui::BeginChild("market_access_board", ImVec2(0, 360), true);
            CardHeader("Provider Marketplace", "Sportsbook and exchange links", View::Arbitrage);
            RenderProviderFilterButton("All apps", "all", IconKind::Sportsbook);
            ImGui::SameLine();
            RenderProviderFilterButton("Sportsbooks", "sportsbook", IconKind::Wallet);
            ImGui::SameLine();
            RenderProviderFilterButton("Kalshi", "kalshi", IconKind::Exchange);
            ImGui::SameLine();
            RenderProviderFilterButton("Live only", "live", IconKind::Live);
            ImGui::Spacing();

            int rendered = 0;
            const float available = ImGui::GetContentRegionAvail().x;
            const float card_w = std::max(245.0f, (available - 24.0f) / 3.0f);
            const std::vector<const aegis::Game*> visible_games = aegis::FilterGames(state_, active_filter_, search_);
            for (const aegis::Game* game_ptr : visible_games)
            {
                const aegis::Game& game = *game_ptr;
                for (const aegis::BetLink& link : game.bet_links)
                {
                    if (!ProviderMatches(link, game))
                        continue;
                    if (rendered > 0 && rendered % 3 != 0)
                        ImGui::SameLine();
                    RenderMarketLinkCard(game, link, ImVec2(card_w, 158.0f));
                    ++rendered;
                    if (rendered >= 12)
                        break;
                }
                if (rendered >= 12)
                    break;
            }
            if (rendered == 0)
                EmptyState("No provider rows match this filter", "Try All apps or connect a live odds provider for deeper market data.");
            ImGui::EndChild();
        }

        void RenderMarketLinkCard(const aegis::Game& game, const aegis::BetLink& link, ImVec2 size)
        {
            ImGui::PushID((game.id + link.provider_key + link.market + link.line).c_str());
            ImGui::BeginChild("provider_link", size, true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const bool exchange = aegis::Lower(link.kind + " " + link.title + " " + link.provider_key).find("kalshi") != std::string::npos ||
                aegis::Lower(link.kind + " " + link.title + " " + link.provider_key).find("exchange") != std::string::npos;
            const std::string provider_label = FirstNonEmpty(link.title, link.provider_key, link.kind, "Provider");
            DrawGeneratedLogoBadge(draw, ImVec2(pos.x, pos.y), ImVec2(38.0f, 38.0f), provider_label, exchange ? IconKind::Exchange : IconKind::Sportsbook, link.available, true);
            ImGui::SetCursorPosX(ImGui::GetCursorPosX() + 50.0f);
            ImGui::PushFont(g_font_bold);
            ImGui::TextUnformatted(provider_label.c_str());
            ImGui::PopFont();
            ImGui::SameLine(ImGui::GetContentRegionAvail().x - 80.0f);
            RenderStateBadge(LinkBadgeText(link));
            ImGui::SetCursorPosX(ImGui::GetCursorPosX() + 50.0f);
            TextMuted(link.kind.empty() ? "Provider" : link.kind.c_str());
            TextMuted(game.matchup.c_str());
            ImGui::Text("%s / %s", link.market.c_str(), link.line.c_str());
            ImGui::Text("Price %s", link.price.empty() ? "--" : link.price.c_str());
            ImGui::SameLine();
            TextGreen(FirstNonEmpty(link.model_edge, link.fair_odds, link.book_probability, "").c_str());
            TextMuted(FirstNonEmpty(link.movement, link.last_update, link.source, "").c_str());
            if (AegisButton(link.url.empty() ? "Provider unavailable" : "Open provider", ImVec2(-1, 30.0f), link.available && !link.url.empty()))
            {
                if (link.url.empty())
                    status_ = "This provider link has no launch URL yet.";
                else
                    aegis::OpenExternalUrl(link.url);
            }
            ImGui::EndChild();
            ImGui::PopID();
        }

        void CardHeader(const char* title, const char* meta, View jump)
        {
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            draw->AddRectFilled(ImVec2(pos.x, pos.y + 4.0f), ImVec2(pos.x + 3.0f, pos.y + 28.0f), Col(0.19f, 0.85f, 0.42f, 0.95f), 999.0f);
            DrawIconBadge(draw, ImVec2(pos.x + 14.0f, pos.y + 1.0f), ImVec2(28.0f, 28.0f), HeaderIconForTitle(title), true);
            ImGui::SetCursorPosX(ImGui::GetCursorPosX() + 52.0f);
            ImGui::PushFont(g_font_bold);
            ImGui::TextUnformatted(title);
            ImGui::PopFont();
            if (meta != nullptr && meta[0] != '\0')
            {
                ImGui::SameLine();
                TextMuted(meta);
            }
            if (jump != active_view_)
            {
                ImGui::SameLine(ImGui::GetWindowWidth() - 94.0f);
                if (PlainLinkButton("View All"))
                    active_view_ = jump;
            }
            ImGui::Spacing();
            const ImVec2 line = ImGui::GetCursorScreenPos();
            draw->AddLine(line, ImVec2(line.x + ImGui::GetContentRegionAvail().x, line.y), Col(0.77f, 0.87f, 0.81f, 0.14f), 1.0f);
            ImGui::Dummy(ImVec2(1, 6));
        }

        IconKind HeaderIconForTitle(const char* title) const
        {
            const std::string lower = aegis::Lower(title == nullptr ? "" : title);
            if (lower.find("live") != std::string::npos || lower.find("events") != std::string::npos)
                return IconKind::Live;
            if (lower.find("pick") != std::string::npos || lower.find("insight") != std::string::npos || lower.find("model") != std::string::npos)
                return IconKind::Brain;
            if (lower.find("market") != std::string::npos || lower.find("analytics") != std::string::npos || lower.find("performance") != std::string::npos || lower.find("movement") != std::string::npos)
                return IconKind::Chart;
            if (lower.find("scenario") != std::string::npos || lower.find("lab") != std::string::npos)
                return IconKind::Chart;
            if (lower.find("alert") != std::string::npos)
                return IconKind::Bell;
            if (lower.find("provider") != std::string::npos)
                return IconKind::Sportsbook;
            if (lower.find("arbitrage") != std::string::npos || lower.find("opportunity") != std::string::npos || lower.find("edge") != std::string::npos)
                return IconKind::Arbitrage;
            if (lower.find("setting") != std::string::npos || lower.find("source") != std::string::npos || lower.find("input") != std::string::npos)
                return IconKind::Settings;
            if (lower.find("risk") != std::string::npos || lower.find("exposure") != std::string::npos || lower.find("ticket") != std::string::npos)
                return IconKind::Wallet;
            return IconKind::Shield;
        }

        IconKind IconForLeague(const std::string& league) const
        {
            const std::string lower = aegis::Lower(league);
            if (lower.find("nba") != std::string::npos || lower.find("basketball") != std::string::npos)
                return IconKind::Basketball;
            if (lower.find("nfl") != std::string::npos || lower.find("football") != std::string::npos)
                return IconKind::Football;
            if (lower.find("mlb") != std::string::npos || lower.find("baseball") != std::string::npos)
                return IconKind::Baseball;
            if (lower.find("nhl") != std::string::npos || lower.find("hockey") != std::string::npos)
                return IconKind::Hockey;
            if (lower.find("soccer") != std::string::npos || lower.find("epl") != std::string::npos || lower.find("mls") != std::string::npos)
                return IconKind::Soccer;
            if (lower.find("ufc") != std::string::npos || lower.find("mma") != std::string::npos || lower.find("fight") != std::string::npos)
                return IconKind::Fight;
            if (lower.find("tennis") != std::string::npos)
                return IconKind::Tennis;
            if (lower.find("esport") != std::string::npos || lower.find("gaming") != std::string::npos)
                return IconKind::Esports;
            return IconKind::Ball;
        }

        std::string EmptyStateStatus(const char* title, const char* detail) const
        {
            const std::string text = aegis::Lower(std::string(title == nullptr ? "" : title) + " " + (detail == nullptr ? "" : detail));
            if (text.find("filter") != std::string::npos || text.find("search") != std::string::npos)
                return "Filter empty";
            if (text.find("odds") != std::string::npos || text.find("provider") != std::string::npos || text.find("feed") != std::string::npos || text.find("key") != std::string::npos)
                return "Provider setup";
            if (text.find("alert") != std::string::npos || text.find("notification") != std::string::npos || text.find("journal") != std::string::npos)
                return "Monitoring quiet";
            if (text.find("calibration") != std::string::npos || text.find("backtest") != std::string::npos || text.find("audit") != std::string::npos || text.find("sample") != std::string::npos)
                return "Awaiting samples";
            if (text.find("ticket") != std::string::npos || text.find("slip") != std::string::npos || text.find("scenario") != std::string::npos || text.find("exposure") != std::string::npos)
                return "No local rows";
            return "Ready when data arrives";
        }

        void EmptyState(const char* title, const char* detail)
        {
            const ImVec2 box(0, 154.0f);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const float width = ImGui::GetContentRegionAvail().x;
            const ImVec2 end(pos.x + width, pos.y + box.y);
            draw->AddRectFilled(pos, end, Col(0.018f, 0.031f, 0.027f, 0.92f), 8.0f);
            draw->AddRectFilled(pos, ImVec2(pos.x + 4.0f, end.y), Col(0.1f, 0.86f, 0.42f, 0.72f), 8.0f, ImDrawFlags_RoundCornersLeft);
            draw->AddRect(pos, end, Col(0.77f, 0.87f, 0.81f, 0.14f), 8.0f);
            DrawGeneratedLogoBadge(draw, ImVec2(pos.x + 18.0f, pos.y + 24.0f), ImVec2(42.0f, 42.0f), title, HeaderIconForTitle(title), false, false);
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 74.0f, pos.y + 24.0f));
            ImGui::PushFont(g_font_bold);
            ImGui::TextColored(V4(1, 1, 1, 1), "%s", title);
            ImGui::PopFont();
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 74.0f, pos.y + 50.0f));
            ImGui::PushTextWrapPos(pos.x + width - 24.0f);
            TextMuted(detail);
            ImGui::PopTextWrapPos();
            const std::string status = EmptyStateStatus(title, detail);
            const ImVec2 chip_pos(pos.x + 74.0f, pos.y + 104.0f);
            const ImVec2 chip_size(std::max(126.0f, ImGui::CalcTextSize(status.c_str()).x + 28.0f), 28.0f);
            draw->AddRectFilled(chip_pos, ImVec2(chip_pos.x + chip_size.x, chip_pos.y + chip_size.y), Col(0.06f, 0.18f, 0.11f, 0.95f), 6.0f);
            draw->AddRect(chip_pos, ImVec2(chip_pos.x + chip_size.x, chip_pos.y + chip_size.y), Col(0.1f, 0.86f, 0.42f, 0.30f), 6.0f);
            ImGui::SetCursorScreenPos(ImVec2(chip_pos.x + 14.0f, chip_pos.y + 6.0f));
            ImGui::TextColored(V4(0.54f, 1.0f, 0.72f, 1.0f), "%s", status.c_str());
            ImGui::Dummy(box);
        }

        std::string SourceBadgeText(const aegis::Game& game) const
        {
            const std::string freshness = aegis::Lower(game.freshness_state + " " + game.feed_age_label + " " + game.source_note);
            const std::string odds = aegis::Lower(game.odds_match_status);
            if (IsBoardStale())
                return "Stale";
            if (freshness.find("fallback") != std::string::npos)
                return "Fallback";
            if (odds.find("unsupported") != std::string::npos)
                return "Unsupported";
            if (odds.find("no odds") != std::string::npos)
                return "No odds";
            if (odds.find("no match") != std::string::npos)
                return "No match";
            if (odds.find("matched no lines") != std::string::npos)
                return "No lines";
            if (odds.find("needs key") != std::string::npos)
                return "Needs key";
            if (odds.find("matched") != std::string::npos)
                return "Matched";
            return "Scoreboard";
        }

        std::string PredictionBadgeText(const aegis::Prediction& prediction) const
        {
            const std::string trust = aegis::Lower(prediction.data_trust + " " + prediction.odds);
            if (prediction.status_key == "final")
                return "Audit";
            if (trust.find("fallback") != std::string::npos)
                return "Fallback";
            if (trust.find("scoreboard only") != std::string::npos)
                return "Scoreboard only";
            if (prediction.odds == "--" || prediction.odds == "Feed snapshot")
                return "No odds";
            if (trust.find("odds") != std::string::npos)
                return "Matched";
            return "Monitor";
        }

        std::string LinkBadgeText(const aegis::BetLink& link) const
        {
            const std::string source = aegis::Lower(link.source + " " + link.note + " " + link.movement);
            if (link.available)
                return "Matched";
            if (source.find("unsupported") != std::string::npos)
                return "Unsupported";
            if (source.find("no odds") != std::string::npos)
                return "No odds";
            if (source.find("no match") != std::string::npos)
                return "No match";
            if (source.find("needs") != std::string::npos || source.find("key") != std::string::npos)
                return "Needs key";
            if (source.find("fallback") != std::string::npos)
                return "Fallback";
            return "Link only";
        }

        void RenderStateBadge(const std::string& label)
        {
            if (label.empty())
                return;
            const std::string lower = aegis::Lower(label);
            ImU32 fill = Col(0.07f, 0.18f, 0.11f, 0.92f);
            ImU32 border = Col(0.32f, 0.92f, 0.50f, 0.42f);
            ImU32 text = Col(0.62f, 1.0f, 0.70f, 1.0f);
            if (lower.find("stale") != std::string::npos || lower.find("fallback") != std::string::npos || lower.find("unsupported") != std::string::npos)
            {
                fill = Col(0.22f, 0.14f, 0.05f, 0.92f);
                border = Col(0.95f, 0.66f, 0.20f, 0.46f);
                text = Col(1.0f, 0.80f, 0.42f, 1.0f);
            }
            else if (lower.find("no ") != std::string::npos || lower.find("needs") != std::string::npos || lower.find("monitor") != std::string::npos)
            {
                fill = Col(0.16f, 0.06f, 0.06f, 0.92f);
                border = Col(1.0f, 0.36f, 0.32f, 0.38f);
                text = Col(1.0f, 0.55f, 0.50f, 1.0f);
            }
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const ImVec2 text_size = ImGui::CalcTextSize(label.c_str());
            const ImVec2 size(std::max(58.0f, text_size.x + 18.0f), 23.0f);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            draw->AddRectFilled(pos, ImVec2(pos.x + size.x, pos.y + size.y), fill, 7.0f);
            draw->AddRect(pos, ImVec2(pos.x + size.x, pos.y + size.y), border, 7.0f);
            draw->AddText(g_font_bold, 13.0f, ImVec2(pos.x + (size.x - text_size.x) * 0.5f, pos.y + 4.0f), text, label.c_str());
            ImGui::Dummy(size);
        }

        void RenderEventCard(const aegis::Game& game, ImVec2 size)
        {
            ImGui::PushID(game.id.c_str());
            ImGui::BeginChild("event", size, true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 head = ImGui::GetCursorScreenPos();
            const std::string clock = game.clock.empty() ? game.status_label : game.clock;
            const ImVec2 clock_size = ImGui::CalcTextSize(clock.c_str());
            draw->AddRectFilled(ImVec2(head.x, head.y), ImVec2(head.x + 84.0f, head.y + 24.0f), Col(0.06f, 0.10f, 0.08f, 1.0f), 7.0f);
            DrawIcon(draw, ImVec2(head.x + 13.0f, head.y + 12.0f), 14.0f, IconForLeague(game.league), Col(0.35f, 1.0f, 0.55f, 0.95f), 1.2f);
            draw->AddText(g_font_bold, 13.0f, ImVec2(head.x + 28.0f, head.y + 5.0f), Col(0.62f, 0.67f, 0.64f, 1.0f), game.league.c_str());
            const float clock_x = head.x + size.x - clock_size.x - 22.0f;
            draw->AddText(g_font_bold, 13.0f, ImVec2(clock_x, head.y + 5.0f), game.status_key == "live" ? Col(1.0f, 0.30f, 0.30f, 1) : Col(0.62f, 0.67f, 0.64f, 1), clock.c_str());
            ImGui::Dummy(ImVec2(1, 32));
            TeamRow(game.away);
            TeamRow(game.home);
            ImGui::Separator();
            ImGui::Text("Spread");
            ImGui::SameLine(ImGui::GetContentRegionAvail().x - 92.0f);
            ImGui::Text("%s   %s", game.spread_favorite.c_str(), game.spread_other.c_str());
            ImGui::Text("Total");
            ImGui::SameLine(ImGui::GetContentRegionAvail().x - 92.0f);
            ImGui::Text("%s   %s", game.total_over.c_str(), game.total_under.c_str());
            const std::string source_line = FirstNonEmpty(game.source_timestamp, game.feed_age_label, "Freshness unknown");
            RenderStateBadge(SourceBadgeText(game));
            ImGui::SameLine();
            TextMuted(source_line.c_str());
            const std::string odds_line = game.odds_match_status.empty()
                ? "Odds match pending"
                : "Odds: " + game.odds_match_status;
            TextMuted(odds_line.c_str());
            const float button_w = (ImGui::GetContentRegionAvail().x - 8.0f) * 0.5f;
            if (AegisButton("Details", ImVec2(button_w, 28), false))
                OpenGameDetail(game.id);
            ImGui::SameLine();
            if (AegisButton("AI breakdown", ImVec2(button_w, 28), false))
                OpenGamePrediction(game.id);
            ImGui::EndChild();
            ImGui::PopID();
        }

        void TeamRow(const aegis::Team& team)
        {
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const float width = ImGui::GetContentRegionAvail().x;
            const std::string seed = team.abbr.empty() ? team.name : team.abbr;
            DrawGeneratedLogoBadge(draw, ImVec2(pos.x, pos.y - 1.0f), ImVec2(26.0f, 26.0f), seed, IconKind::Ball, true, true);
            draw->AddText(g_font_regular, 14.0f, ImVec2(pos.x + 36.0f, pos.y + 4.0f), Col(0.92f, 0.97f, 0.94f, 1.0f), team.name.c_str());
            const std::string score = std::to_string(team.score);
            const ImVec2 score_size = ImGui::CalcTextSize(score.c_str());
            draw->AddText(g_font_bold, 14.0f, ImVec2(pos.x + width - score_size.x, pos.y + 4.0f), Col(0.92f, 0.97f, 0.94f, 1.0f), score.c_str());
            ImGui::Dummy(ImVec2(width, 27.0f));
        }

        void RenderPredictionTable(int limit, bool winner_column)
        {
            const std::vector<int> indexes = VisiblePredictionIndexes();
            if (indexes.empty())
            {
                EmptyState("No picks match the active filters", "Clear filters or lower the confidence threshold to restore the model table.");
                return;
            }
            if (ImGui::BeginTable("predictions", 8, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn(winner_column ? "Predicted Winner" : "Pick", ImGuiTableColumnFlags_WidthStretch, 1.4f);
                ImGui::TableSetupColumn("Match", ImGuiTableColumnFlags_WidthStretch, 1.0f);
                ImGui::TableSetupColumn("Market", ImGuiTableColumnFlags_WidthFixed, 105.0f);
                ImGui::TableSetupColumn("Confidence", ImGuiTableColumnFlags_WidthFixed, 135.0f);
                ImGui::TableSetupColumn("Odds", ImGuiTableColumnFlags_WidthFixed, 72.0f);
                ImGui::TableSetupColumn("Edge", ImGuiTableColumnFlags_WidthFixed, 80.0f);
                ImGui::TableSetupColumn("Expected Value", ImGuiTableColumnFlags_WidthFixed, 112.0f);
                ImGui::TableSetupColumn("", ImGuiTableColumnFlags_WidthFixed, 72.0f);
                ImGui::TableHeadersRow();

                int rendered = 0;
                for (const int index : indexes)
                {
                    if (rendered++ >= limit)
                        break;
                    const aegis::Prediction& p = state_.predictions[static_cast<size_t>(index)];
                    ImGui::PushID(index);
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0);
                    {
                        ImDrawList* draw = ImGui::GetWindowDrawList();
                        const ImVec2 p0 = ImGui::GetCursorScreenPos();
                        DrawIcon(draw, ImVec2(p0.x + 8.0f, p0.y + 9.0f), 14.0f, winner_column ? IconKind::Trophy : IconKind::Brain, Col(0.25f, 0.88f, 0.48f, 1.0f), 1.25f);
                        ImGui::Dummy(ImVec2(20.0f, 1.0f));
                        ImGui::SameLine();
                    }
                    ImGui::TextUnformatted((winner_column ? aegis::PredictionWinner(p, state_) : p.pick).c_str());
                    RenderStateBadge(PredictionBadgeText(p));
                    ImGui::SameLine();
                    TextMuted(FirstNonEmpty(p.data_trust, "Data trust pending", "").c_str());
                    ImGui::TableSetColumnIndex(1);
                    ImGui::TextUnformatted(p.matchup.c_str());
                    TextMuted(FirstNonEmpty(p.source_timestamp, "Source pending", "").c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(p.market.c_str());
                    ImGui::TableSetColumnIndex(3);
                    ConfidenceBar(p.confidence_value, p.confidence);
                    if (!p.confidence_band.empty())
                        TextMuted(p.confidence_band.c_str());
                    ImGui::TableSetColumnIndex(4); ImGui::TextUnformatted(p.odds.c_str());
                    ImGui::TableSetColumnIndex(5); TextGreen(p.edge.c_str());
                    ImGui::TableSetColumnIndex(6); TextGreen(p.expected_value.c_str());
                    ImGui::TableSetColumnIndex(7);
                    if (PlainLinkButton("Open"))
                    {
                        selected_prediction_ = index;
                        selected_game_id_ = p.game_id;
                        show_drawer_ = true;
                    }
                    ImGui::PopID();
                }
                ImGui::EndTable();
            }
        }

        void ConfidenceBar(int confidence, const std::string& label)
        {
            const float fraction = std::clamp(confidence / 100.0f, 0.0f, 1.0f);
            ImGui::ProgressBar(fraction, ImVec2(76, 10), "");
            ImGui::SameLine();
            ImGui::TextUnformatted(label.c_str());
        }

        void RenderInfoList(const std::vector<aegis::InfoItem>& items, int limit)
        {
            int count = 0;
            for (const aegis::InfoItem& item : items)
            {
                if (count++ >= limit)
                    break;
                const std::string title = FirstNonEmpty(item.name, item.label, item.book, "Signal");
                const std::string detail = FirstNonEmpty(item.detail, item.value, item.line, item.source);
                ImDrawList* draw = ImGui::GetWindowDrawList();
                const ImVec2 icon_pos = ImGui::GetCursorScreenPos();
                DrawIcon(draw, ImVec2(icon_pos.x + 8.0f, icon_pos.y + 9.0f), 14.0f, HeaderIconForTitle(title.c_str()), Col(0.93f, 0.66f, 0.20f, 1.0f), 1.2f);
                ImGui::Dummy(ImVec2(19.0f, 1.0f));
                ImGui::SameLine();
                ImGui::BeginGroup();
                ImGui::TextUnformatted(title.c_str());
                TextMuted(detail.c_str());
                ImGui::EndGroup();
                const std::string side = FirstNonEmpty(item.time, item.latency, item.state, item.tag);
                if (!side.empty())
                {
                    ImGui::SameLine(ImGui::GetContentRegionAvail().x - 45.0f);
                    TextMuted(side.c_str());
                }
                ImGui::Spacing();
            }
        }

        void RenderInfoGridCard(const char* title, const std::vector<aegis::InfoItem>& items, float width, float height)
        {
            ImGui::BeginChild(title, ImVec2(width <= 0 ? 0 : width, height), true);
            CardHeader(title, "", active_view_);
            const float cell_w = std::max(190.0f, (ImGui::GetContentRegionAvail().x - 12.0f) * 0.5f);
            for (size_t i = 0; i < items.size(); ++i)
            {
                if (i % 2 != 0)
                    ImGui::SameLine();
                RenderInfoChip(items[i], ImVec2(cell_w, 96.0f));
            }
            ImGui::EndChild();
        }

        void RenderInfoChip(const aegis::InfoItem& item, ImVec2 size)
        {
            ImGui::PushID(&item);
            ImGui::BeginChild("chip", size, true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const std::string title = FirstNonEmpty(item.name, item.label, item.book, "Signal");
            const std::string value = FirstNonEmpty(item.value, item.weight, item.state, item.status, item.line, item.odds);
            const std::string detail = FirstNonEmpty(item.detail, item.latency, item.env, item.source);
            DrawGeneratedLogoBadge(draw, ImVec2(pos.x, pos.y + 1.0f), ImVec2(34.0f, 34.0f), title, HeaderIconForTitle(title.c_str()), true, false);
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 46.0f, pos.y + 4.0f));
            TextMuted(title.c_str());
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 46.0f, pos.y + 27.0f));
            ImGui::TextColored(V4(1, 1, 1, 1), "%s", value.c_str());
            if (!item.tag.empty())
            {
                ImGui::SetCursorScreenPos(ImVec2(pos.x + 46.0f, pos.y + 49.0f));
                TextGreen(item.tag.c_str());
            }
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 10.0f, pos.y + 62.0f));
            ImGui::PushTextWrapPos(pos.x + size.x - 12.0f);
            TextMuted(detail.c_str());
            ImGui::PopTextWrapPos();
            ImGui::EndChild();
            ImGui::PopID();
        }

        std::string FirstNonEmpty(const std::string& a, const std::string& b, const std::string& c, const std::string& d = "", const std::string& e = "", const std::string& f = "") const
        {
            if (!a.empty()) return a;
            if (!b.empty()) return b;
            if (!c.empty()) return c;
            if (!d.empty()) return d;
            if (!e.empty()) return e;
            return f;
        }

        void RenderStatusbar(ImVec2 size, float status_h)
        {
            ImGui::SetCursorPos(ImVec2(0, size.y - status_h));
            ImGui::BeginChild("statusbar", ImVec2(size.x, status_h), true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            draw->AddRectFilled(ImVec2(pos.x, pos.y - 2.0f), ImVec2(pos.x + 4.0f, pos.y + status_h - 12.0f), Col(0.19f, 0.85f, 0.42f, 0.95f), 999.0f);
            ImGui::SetCursorPosX(ImGui::GetCursorPosX() + 12.0f);
            const int max_tape = std::min(4, static_cast<int>(state_.tape.size()));
            for (int i = 0; i < max_tape; ++i)
            {
                const aegis::InfoItem& item = state_.tape[static_cast<size_t>(i)];
                TextGreen(FirstNonEmpty(item.name, item.label, "Feed").c_str());
                ImGui::SameLine();
                ImGui::TextUnformatted(FirstNonEmpty(item.value, item.detail, item.state, "").c_str());
                ImGui::SameLine();
                TextMuted(FirstNonEmpty(item.state, item.tag, item.latency, "").c_str());
                ImGui::SameLine(0, 28);
            }
            ImGui::SetCursorPosX(std::max(ImGui::GetCursorPosX(), size.x - 270.0f));
            ImGui::SameLine();
            const std::string source_state = refresh_in_flight_ ? "Syncing Direct Sources" : state_.source_badge + " / Trust " + std::to_string(DataTrustScore()) + "%";
            TextGreen(source_state.c_str());
            ImGui::EndChild();
        }

        void RenderPredictionDrawer(ImVec2 size)
        {
            if (selected_prediction_ < 0 || selected_prediction_ >= static_cast<int>(state_.predictions.size()))
            {
                show_drawer_ = false;
                return;
            }
            const aegis::Prediction& p = state_.predictions[static_cast<size_t>(selected_prediction_)];
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 origin = ImGui::GetWindowPos();
            draw->AddRectFilled(origin, ImVec2(origin.x + size.x, origin.y + size.y), Col(0, 0, 0, 0.48f));
            const float drawer_w = std::min(560.0f, size.x * 0.42f);
            ImGui::SetCursorPos(ImVec2(size.x - drawer_w, 0));
            ImGui::BeginChild("drawer", ImVec2(drawer_w, size.y), true);
            if (AegisButton("Close", ImVec2(92, 36), false))
                show_drawer_ = false;
            ImGui::TextColored(V4(0.19f, 0.85f, 0.42f, 1), "%s / %s / %s", p.league.c_str(), p.market.c_str(), p.status_label.c_str());
            ImGui::PushFont(g_font_title);
            ImGui::TextWrapped("%s", p.pick.c_str());
            ImGui::PopFont();
            RenderStateBadge(PredictionBadgeText(p));
            ImGui::SameLine();
            TextMuted(FirstNonEmpty(p.data_trust, p.source_timestamp, "Source pending").c_str());
            ImGui::TextWrapped("%s", p.reason.c_str());
            if (AegisIconButton("View Markets", IconKind::Sportsbook, ImVec2(156.0f, 38.0f), false))
            {
                provider_filter_ = "all";
                active_view_ = View::Arbitrage;
                show_drawer_ = false;
            }
            ImGui::SameLine();
            if (AegisIconButton(IsWatched(p.game_id) ? "Watching" : "Watch", IconKind::Bell, ImVec2(128.0f, 38.0f), IsWatched(p.game_id)))
                AddWatch(p.game_id);
            ImGui::SameLine();
            if (AegisIconButton("Preview", IconKind::Wallet, ImVec2(128.0f, 38.0f), false))
            {
                AddWatch(p.game_id);
                active_view_ = View::Watchlist;
                show_drawer_ = false;
            }
            if (AegisIconButton("Detail", IconKind::Trophy, ImVec2(116.0f, 38.0f), false))
            {
                OpenGameDetail(p.game_id);
                show_drawer_ = false;
            }
            ImGui::SameLine();
            if (AegisIconButton("Scenario", IconKind::Chart, ImVec2(136.0f, 38.0f), false))
            {
                selected_game_id_ = p.game_id;
                active_view_ = View::Scenario;
                show_drawer_ = false;
            }
            ImGui::SeparatorText("Score");
            RenderDrawerScore("Final confidence", p.confidence, "Informational probability estimate, not a guarantee.");
            ImGui::SameLine();
            RenderDrawerScore("Fair probability", p.fair_probability.empty() ? p.fair_odds : p.fair_probability, "Model probability before comparing available market prices.");
            ImGui::SameLine();
            RenderDrawerScore("Market disagreement", p.edge, p.risk.c_str());
            ImGui::SeparatorText("Model facts");
            const std::string input_count = std::to_string(p.input_count) + " source inputs";
            const std::string penalty = "-" + std::to_string(p.missing_input_penalty) + " pts";
            RenderDrawerScore("Model", p.model_version.empty() ? "Aegis Source Model" : p.model_version, "Runs locally inside the desktop app.");
            ImGui::SameLine();
            RenderDrawerScore("Inputs counted", input_count, "Scoreboard, status, record, market, and live-score inputs when present.");
            ImGui::SameLine();
            RenderDrawerScore("Missing penalty", penalty, "Keeps confidence conservative until injury, lineup, and tracking feeds are connected.");
            ImGui::SeparatorText("Team comparison");
            RenderTeamComparison(p.comparison);
            ImGui::SeparatorText("How the rating is built");
            RenderInfoList(p.steps, 8);
            ImGui::SeparatorText("Factor checklist");
            for (const aegis::InfoItem& item : p.factors)
                RenderInfoChip(item, ImVec2(-1, 92));
            ImGui::SeparatorText("Data gaps to configure");
            RenderInfoList(p.missing_inputs, 6);
            ImGui::EndChild();
        }

        void RenderTeamComparison(const aegis::TeamComparison& comparison)
        {
            if (comparison.away.name.empty() && comparison.home.name.empty() && comparison.rows.empty())
            {
                TextMuted("Team comparison data will appear when direct provider feeds return a matchup with enough context.");
                return;
            }

            const float width = ImGui::GetContentRegionAvail().x;
            const float cell = (width - 16.0f) / 3.0f;
            RenderComparisonTeam("Away", comparison.away, cell);
            ImGui::SameLine();
            ImGui::BeginChild("baseline", ImVec2(cell, 112), true, ImGuiWindowFlags_NoScrollbar);
            TextMuted("Baseline");
            ImGui::PushFont(g_font_bold);
            ImGui::TextUnformatted("50 / 50");
            ImGui::PopFont();
            ImGui::PushTextWrapPos(ImGui::GetCursorPosX() + cell - 16.0f);
            TextMuted(comparison.summary.empty() ? "Aegis starts neutral, then compares both teams with available evidence." : comparison.summary.c_str());
            ImGui::PopTextWrapPos();
            ImGui::EndChild();
            ImGui::SameLine();
            RenderComparisonTeam("Home", comparison.home, cell);

            if (comparison.rows.empty())
                return;

            ImGui::Spacing();
            if (ImGui::BeginTable("comparison_rows", 4, ImGuiTableFlags_RowBg | ImGuiTableFlags_BordersInnerH | ImGuiTableFlags_SizingStretchProp))
            {
                ImGui::TableSetupColumn("Factor", ImGuiTableColumnFlags_WidthStretch, 1.55f);
                ImGui::TableSetupColumn(comparison.away.abbr.empty() ? "Away" : comparison.away.abbr.c_str(), ImGuiTableColumnFlags_WidthStretch, 0.8f);
                ImGui::TableSetupColumn(comparison.home.abbr.empty() ? "Home" : comparison.home.abbr.c_str(), ImGuiTableColumnFlags_WidthStretch, 0.8f);
                ImGui::TableSetupColumn("Read", ImGuiTableColumnFlags_WidthStretch, 0.85f);
                ImGui::TableHeadersRow();
                const int max_rows = std::min(10, static_cast<int>(comparison.rows.size()));
                for (int i = 0; i < max_rows; ++i)
                {
                    const aegis::InfoItem& row = comparison.rows[static_cast<size_t>(i)];
                    ImGui::TableNextRow();
                    ImGui::TableSetColumnIndex(0);
                    ImGui::TextUnformatted(FirstNonEmpty(row.label, row.name, "Signal").c_str());
                    if (!row.detail.empty())
                    {
                        ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "%s", row.detail.c_str());
                    }
                    ImGui::TableSetColumnIndex(1); ImGui::TextUnformatted(row.away.empty() ? "--" : row.away.c_str());
                    ImGui::TableSetColumnIndex(2); ImGui::TextUnformatted(row.home.empty() ? "--" : row.home.c_str());
                    ImGui::TableSetColumnIndex(3); TextGreen(row.edge.empty() ? "Even" : row.edge.c_str());
                }
                ImGui::EndTable();
            }
        }

        void RenderComparisonTeam(const char* label, const aegis::Team& team, float width)
        {
            ImGui::BeginChild(label, ImVec2(width, 112), true, ImGuiWindowFlags_NoScrollbar);
            ImDrawList* draw = ImGui::GetWindowDrawList();
            const ImVec2 pos = ImGui::GetCursorScreenPos();
            const std::string seed = team.abbr.empty() ? team.name : team.abbr;
            DrawGeneratedLogoBadge(draw, ImVec2(pos.x, pos.y + 22.0f), ImVec2(42.0f, 42.0f), seed.empty() ? label : seed, IconKind::Ball, true, true);
            TextMuted(label);
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 54.0f, pos.y + 18.0f));
            ImGui::PushFont(g_font_bold);
            ImGui::TextUnformatted((team.abbr.empty() ? team.name : team.abbr).c_str());
            ImGui::PopFont();
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 54.0f, pos.y + 42.0f));
            TextMuted(team.name.c_str());
            ImGui::SetCursorScreenPos(ImVec2(pos.x + 54.0f, pos.y + 64.0f));
            TextGreen(team.probability.empty() ? "50%" : team.probability.c_str());
            if (team.rating > 0)
            {
                ImGui::SetCursorScreenPos(ImVec2(pos.x + 10.0f, pos.y + 88.0f));
                ImGui::TextColored(V4(0.62f, 0.67f, 0.64f, 1.0f), "%d/100 Aegis rating", team.rating);
            }
            else if (!team.record.empty())
            {
                ImGui::SetCursorScreenPos(ImVec2(pos.x + 10.0f, pos.y + 88.0f));
                TextMuted(team.record.c_str());
            }
            ImGui::EndChild();
        }

        void RenderDrawerScore(const char* title, const std::string& value, const char* detail)
        {
            ImGui::BeginChild(title, ImVec2((ImGui::GetContentRegionAvail().x - 16.0f) / 3.0f, 104), true, ImGuiWindowFlags_NoScrollbar);
            TextMuted(title);
            ImGui::PushFont(g_font_bold);
            TextGreen(value.c_str());
            ImGui::PopFont();
            TextMuted(detail);
            ImGui::EndChild();
        }

        void OpenGamePrediction(const std::string& game_id)
        {
            selected_game_id_ = game_id;
            for (int i = 0; i < static_cast<int>(state_.predictions.size()); ++i)
            {
                if (state_.predictions[static_cast<size_t>(i)].game_id == game_id)
                {
                    selected_prediction_ = i;
                    show_drawer_ = true;
                    return;
                }
            }
            selected_prediction_ = state_.predictions.empty() ? -1 : 0;
            show_drawer_ = selected_prediction_ >= 0;
        }

        void OpenGameDetail(const std::string& game_id)
        {
            selected_game_id_ = game_id;
            for (int i = 0; i < static_cast<int>(state_.predictions.size()); ++i)
            {
                if (state_.predictions[static_cast<size_t>(i)].game_id == game_id)
                {
                    selected_prediction_ = i;
                    break;
                }
            }
            active_view_ = View::Details;
        }

        void AttemptLogin(bool manual)
        {
            const std::string username = aegis::Trim(login_user_);
            const std::string password = login_password_;
            if (username.empty() || password.empty())
            {
                status_ = "Enter your username and password.";
                return;
            }

            status_ = "Signing in...";
            const std::string body = "{\"identifier\":\"" + aegis::EscapeJson(username) + "\",\"password\":\"" + aegis::EscapeJson(password) + "\"}";
            const std::string login_url = aegis::JoinUrl(config_.auth_base_url, config_.login_path);
            aegis::HttpResponse response = aegis::HttpPostJson(login_url, body);
            if (!response.error.empty())
            {
                aegis::AppendDiagnosticLine("login request failed: " + response.error);
                auth_offline_ = true;
                status_ = "Could not reach auth service at " + config_.auth_base_url + ". Start the website/auth server, then press Check Auth.";
                return;
            }

            aegis::JsonParseResult parsed = aegis::ParseJson(response.body);
            if (!parsed.ok || (response.status_code >= 400 && parsed.value["code"].AsString() == "missing_credentials"))
            {
                const std::string form = "identifier=" + aegis::UrlEncode(username) + "&password=" + aegis::UrlEncode(password);
                response = aegis::HttpPostForm(login_url, form);
                parsed = aegis::ParseJson(response.body);
            }

            if (response.status_code < 200 || response.status_code >= 300 || !parsed.ok || !parsed.value["ok"].AsBool(false))
            {
                aegis::AppendDiagnosticLine("login rejected status=" + std::to_string(response.status_code));
                auth_offline_ = response.status_code == 503 || (parsed.ok && aegis::Lower(parsed.value["message"].AsString()).find("database") != std::string::npos);
                if (parsed.ok)
                    status_ = "Login failed (" + std::to_string(response.status_code) + "): " + parsed.value["message"].AsString("Login failed.") + (auth_offline_ ? " Check the auth database/server." : "");
                else
                    status_ = "Login failed (" + std::to_string(response.status_code) + "): " + parsed.error;
                authenticated_ = false;
                return;
            }

            cookie_header_ = aegis::CookieHeaderFromSetCookies(response.set_cookies);
            if (cookie_header_.empty())
            {
                const std::string form = "identifier=" + aegis::UrlEncode(username) + "&password=" + aegis::UrlEncode(password);
                response = aegis::HttpPostForm(login_url, form);
                parsed = aegis::ParseJson(response.body);
                cookie_header_ = aegis::CookieHeaderFromSetCookies(response.set_cookies);
            }
            aegis::AppendDiagnosticLine("login accepted status=" + std::to_string(response.status_code) + " cookie=" + std::string(cookie_header_.empty() ? "0" : "1"));
            auth_offline_ = false;
            username_ = parsed.value["username"].AsString(username);
            authenticated_ = true;
            if (remember_me_ && config_.remember_credentials)
                aegis::SaveRememberedCredentials(username, password);
            else
                aegis::DeleteRememberedCredentials();
            status_ = manual ? "Signed in. Preparing native live board..." : "Remembered login accepted. Preparing native live board...";
            BeginSportsRefresh(true, "Building your sports betting workspace", "Fetching direct scoreboard hosts, model picks, provider links, and matchup intelligence.");
        }

        void BeginSportsRefresh(bool initial, const std::string& headline, const std::string& detail)
        {
            if (refresh_in_flight_)
            {
                status_ = "A sports sync is already running.";
                return;
            }

            const int request_id = ++refresh_request_id_;
            const aegis::Config config = config_;
            refresh_in_flight_ = true;
            initial_sync_ = initial;
            sync_started_ = std::chrono::steady_clock::now();
            sync_headline_ = headline.empty() ? "Syncing Aegis market intelligence" : headline;
            sync_detail_ = detail.empty() ? "Refreshing sports state from direct provider feeds." : detail;
            status_ = initial ? "Preparing live markets..." : "Refreshing live markets...";

            refresh_future_ = std::async(std::launch::async, [config, request_id]() {
                const auto started = std::chrono::steady_clock::now();
                RefreshResult result;
                result.request_id = request_id;
                result.refresh_label = aegis::NowTimeLabel();

                result.ok = true;
                const auto board_started = std::chrono::steady_clock::now();
                result.state = aegis::BuildNativeSportsState(config.tracked_games, config.model_count, config.refresh_seconds, config.odds_api_key);
                const int board_ms = static_cast<int>(std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::steady_clock::now() - board_started).count());
                result.provider_latency_ms["scoreboard"] = board_ms;
                if (!aegis::Trim(config.odds_api_key).empty())
                    result.provider_latency_ms["odds_api"] = board_ms;
                result.provider_latency_ms["kalshi"] = 0;
                result.optional_feeds = CollectConfiguredOptionalFeeds(config, &result.provider_latency_ms);
                aegis::ApplyOptionalFeedSignals(result.state, result.optional_feeds);
                result.elapsed_ms = static_cast<int>(std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::steady_clock::now() - started).count());
                result.diagnostic = "native sports synchronized games=" + std::to_string(result.state.games.size()) +
                    " predictions=" + std::to_string(result.state.predictions.size()) +
                    " optional_feeds=" + std::to_string(result.optional_feeds.size());
                result.status = "Native sports board synchronized. Last refresh " + result.refresh_label + ".";
                return result;
            });
        }

        void PollSportsRefresh()
        {
            if (!refresh_in_flight_ || !refresh_future_.valid())
                return;

            if (refresh_future_.wait_for(std::chrono::milliseconds(0)) != std::future_status::ready)
                return;

            if (initial_sync_ && std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::steady_clock::now() - sync_started_).count() < 1100)
                return;

            RefreshResult result;
            try
            {
                result = refresh_future_.get();
            }
            catch (const std::exception& ex)
            {
                result.status = std::string("Sports refresh paused: ") + ex.what();
                result.diagnostic = std::string("sports refresh exception: ") + ex.what();
            }
            catch (...)
            {
                result.status = "Sports refresh paused by an unknown error.";
                result.diagnostic = "sports refresh unknown exception";
            }

            refresh_in_flight_ = false;
            initial_sync_ = false;
            if (result.request_id != 0 && result.request_id != refresh_request_id_)
                return;

            if (!result.diagnostic.empty())
                aegis::AppendDiagnosticLine(result.diagnostic);

            last_refresh_ = std::chrono::steady_clock::now();
            if (!result.refresh_label.empty())
                last_refresh_label_ = result.refresh_label;
            UpdateProviderRefreshRecordsFromResult(result);

            if (!result.ok)
            {
                status_ = result.status.empty() ? "Sports refresh paused." : result.status;
                AppendProviderHealthSnapshot("refresh_failed");
                return;
            }

            UpdateAdapterProbesFromRefresh(result.optional_feeds);
            ProcessRefreshNotifications(result.state);
            last_change_summary_ = BuildRefreshChangeSummary(state_, result.state);
            state_ = std::move(result.state);
            last_refresh_elapsed_ms_ = result.elapsed_ms;
            if (!setup_guided_once_ && !SetupComplete() && (active_view_ == View::Dashboard || active_view_ == View::Live))
            {
                active_view_ = View::Setup;
                setup_guided_once_ = true;
                status_ = "Setup guidance is open. Finish the readiness checklist before wider use.";
            }
            else
            {
                status_ = result.status;
            }
            AppendProviderHealthSnapshot("refresh");
        }
    };

    void ApplyTheme()
    {
        ImGuiStyle& style = ImGui::GetStyle();
        ImGui::StyleColorsDark();
        style.WindowRounding = 0.0f;
        style.ChildRounding = 10.0f;
        style.FrameRounding = 8.0f;
        style.PopupRounding = 8.0f;
        style.ScrollbarRounding = 8.0f;
        style.ScrollbarSize = 8.0f;
        style.GrabRounding = 8.0f;
        style.FrameBorderSize = 1.0f;
        style.ChildBorderSize = 1.0f;
        style.WindowBorderSize = 0.0f;
        style.ItemSpacing = ImVec2(12.0f, 9.0f);
        style.FramePadding = ImVec2(13.0f, 9.0f);
        style.WindowPadding = ImVec2(12.0f, 12.0f);
        style.ChildBorderSize = 1.0f;

        ImVec4* colors = style.Colors;
        colors[ImGuiCol_Text] = V4(0.94f, 0.98f, 0.95f, 1.0f);
        colors[ImGuiCol_TextDisabled] = V4(0.42f, 0.48f, 0.45f, 1.0f);
        colors[ImGuiCol_WindowBg] = V4(0.012f, 0.022f, 0.023f, 1.0f);
        colors[ImGuiCol_ChildBg] = V4(0.026f, 0.043f, 0.041f, 0.94f);
        colors[ImGuiCol_PopupBg] = V4(0.026f, 0.043f, 0.041f, 0.99f);
        colors[ImGuiCol_Border] = V4(0.78f, 0.90f, 0.84f, 0.18f);
        colors[ImGuiCol_Separator] = V4(0.78f, 0.90f, 0.84f, 0.15f);
        colors[ImGuiCol_ScrollbarBg] = V4(0.015f, 0.025f, 0.025f, 0.50f);
        colors[ImGuiCol_ScrollbarGrab] = V4(0.11f, 0.20f, 0.17f, 0.86f);
        colors[ImGuiCol_ScrollbarGrabHovered] = V4(0.18f, 0.38f, 0.22f, 0.94f);
        colors[ImGuiCol_ScrollbarGrabActive] = V4(0.22f, 0.58f, 0.30f, 1.0f);
        colors[ImGuiCol_FrameBg] = V4(0.040f, 0.060f, 0.058f, 0.96f);
        colors[ImGuiCol_FrameBgHovered] = V4(0.060f, 0.115f, 0.090f, 0.98f);
        colors[ImGuiCol_FrameBgActive] = V4(0.070f, 0.165f, 0.115f, 1.0f);
        colors[ImGuiCol_CheckMark] = V4(0.19f, 0.85f, 0.42f, 1.0f);
        colors[ImGuiCol_SliderGrab] = V4(0.19f, 0.85f, 0.42f, 1.0f);
        colors[ImGuiCol_Header] = V4(0.08f, 0.18f, 0.11f, 0.78f);
        colors[ImGuiCol_HeaderHovered] = V4(0.10f, 0.25f, 0.15f, 0.88f);
        colors[ImGuiCol_HeaderActive] = V4(0.12f, 0.31f, 0.18f, 1.0f);
        colors[ImGuiCol_TableHeaderBg] = V4(0.035f, 0.055f, 0.052f, 1.0f);
        colors[ImGuiCol_TableRowBg] = V4(0.025f, 0.040f, 0.038f, 0.48f);
        colors[ImGuiCol_TableRowBgAlt] = V4(0.040f, 0.060f, 0.058f, 0.54f);
        colors[ImGuiCol_Button] = V4(0.042f, 0.065f, 0.060f, 0.96f);
        colors[ImGuiCol_ButtonHovered] = V4(0.085f, 0.190f, 0.125f, 0.98f);
        colors[ImGuiCol_ButtonActive] = V4(0.105f, 0.300f, 0.170f, 1.0f);
        colors[ImGuiCol_PlotHistogram] = V4(0.19f, 0.85f, 0.42f, 1.0f);
        colors[ImGuiCol_PlotLines] = V4(0.55f, 0.88f, 1.0f, 1.0f);
        colors[ImGuiCol_TextSelectedBg] = V4(0.20f, 0.80f, 0.42f, 0.24f);
    }

    HICON CreateAegisWindowIcon(int size)
    {
        HDC screen = GetDC(nullptr);
        HDC dc = CreateCompatibleDC(screen);
        HBITMAP color = CreateCompatibleBitmap(screen, size, size);
        HBITMAP old_color = static_cast<HBITMAP>(SelectObject(dc, color));

        RECT rect{ 0, 0, size, size };
        HBRUSH bg = CreateSolidBrush(RGB(3, 11, 8));
        FillRect(dc, &rect, bg);
        DeleteObject(bg);

        HPEN glow = CreatePen(PS_SOLID, std::max(1, size / 12), RGB(40, 210, 95));
        HPEN old_pen = static_cast<HPEN>(SelectObject(dc, glow));
        HBRUSH hollow = static_cast<HBRUSH>(GetStockObject(NULL_BRUSH));
        HBRUSH old_brush = static_cast<HBRUSH>(SelectObject(dc, hollow));

        POINT shield[6] = {
            { size / 2, size / 7 },
            { size * 6 / 7, size * 2 / 7 },
            { size * 5 / 6, size * 2 / 3 },
            { size / 2, size * 6 / 7 },
            { size / 6, size * 2 / 3 },
            { size / 7, size * 2 / 7 }
        };
        Polygon(dc, shield, 6);

        HFONT font = CreateFontW(-static_cast<int>(size * 0.58f), 0, 0, 0, FW_BOLD, FALSE, FALSE, FALSE, DEFAULT_CHARSET,
            OUT_OUTLINE_PRECIS, CLIP_DEFAULT_PRECIS, CLEARTYPE_QUALITY, VARIABLE_PITCH, L"Segoe UI");
        HFONT old_font = static_cast<HFONT>(SelectObject(dc, font));
        SetBkMode(dc, TRANSPARENT);
        SetTextColor(dc, RGB(80, 255, 135));
        DrawTextW(dc, L"A", 1, &rect, DT_CENTER | DT_VCENTER | DT_SINGLELINE);

        SelectObject(dc, old_font);
        DeleteObject(font);
        SelectObject(dc, old_brush);
        SelectObject(dc, old_pen);
        DeleteObject(glow);
        SelectObject(dc, old_color);

        HBITMAP mask = CreateBitmap(size, size, 1, 1, nullptr);
        ICONINFO info{};
        info.fIcon = TRUE;
        info.hbmColor = color;
        info.hbmMask = mask;
        HICON icon = CreateIconIndirect(&info);

        DeleteObject(mask);
        DeleteObject(color);
        DeleteDC(dc);
        ReleaseDC(nullptr, screen);
        return icon;
    }

    bool HasCommandFlag(const std::string& command_line, const std::string& flag)
    {
        return command_line.find(flag) != std::string::npos;
    }

    std::string CommandLineOption(const std::string& command_line, const std::string& option, const std::string& fallback)
    {
        const size_t start = command_line.find(option);
        if (start == std::string::npos)
            return fallback;
        const size_t value_start = start + option.size();
        size_t value_end = command_line.find_first_of(" \t\r\n\"", value_start);
        if (value_end == std::string::npos)
            value_end = command_line.size();
        std::string value = command_line.substr(value_start, value_end - value_start);
        return value.empty() ? fallback : value;
    }
}

int WINAPI wWinMain(HINSTANCE hInstance, HINSTANCE, PWSTR, int)
{
    SetProcessDpiAwarenessContext(DPI_AWARENESS_CONTEXT_PER_MONITOR_AWARE_V2);

    HICON app_icon = CreateAegisWindowIcon(32);
    HICON app_icon_small = CreateAegisWindowIcon(16);
    WNDCLASSEXW wc = { sizeof(wc), CS_CLASSDC, WndProc, 0L, 0L, hInstance, app_icon, LoadCursor(nullptr, IDC_ARROW), nullptr, nullptr, L"AegisSportsBettingAI", app_icon_small };
    RegisterClassExW(&wc);
    const DWORD window_style = WS_POPUP | WS_THICKFRAME | WS_MINIMIZEBOX | WS_MAXIMIZEBOX | WS_SYSMENU;
    HWND hwnd = CreateWindowExW(WS_EX_APPWINDOW, wc.lpszClassName, L"Aegis Sports Betting AI", window_style, 100, 100, 1600, 940, nullptr, nullptr, wc.hInstance, nullptr);
    g_AppHwnd = hwnd;
    ConfigureBorderlessWindow(hwnd);
    SendMessageW(hwnd, WM_SETICON, ICON_BIG, reinterpret_cast<LPARAM>(app_icon));
    SendMessageW(hwnd, WM_SETICON, ICON_SMALL, reinterpret_cast<LPARAM>(app_icon_small));

    if (!CreateDeviceD3D(hwnd))
    {
        CleanupDeviceD3D();
        DestroyWindow(hwnd);
        g_AppHwnd = nullptr;
        UnregisterClassW(wc.lpszClassName, wc.hInstance);
        if (app_icon)
            DestroyIcon(app_icon);
        if (app_icon_small)
            DestroyIcon(app_icon_small);
        return 1;
    }

    ShowWindow(hwnd, SW_SHOWDEFAULT);
    UpdateWindow(hwnd);

    IMGUI_CHECKVERSION();
    ImGui::CreateContext();
    ImGuiIO& io = ImGui::GetIO();
    io.ConfigFlags |= ImGuiConfigFlags_NavEnableKeyboard;
    io.IniFilename = nullptr;

    ImGui_ImplWin32_Init(hwnd);
    ImGui_ImplDX11_Init(g_pd3dDevice, g_pd3dDeviceContext);

    const char* segoe = "C:\\Windows\\Fonts\\segoeui.ttf";
    const char* segoe_bold = "C:\\Windows\\Fonts\\segoeuib.ttf";
    g_font_regular = io.Fonts->AddFontFromFileTTF(segoe, 16.0f);
    g_font_bold = io.Fonts->AddFontFromFileTTF(segoe_bold, 16.0f);
    g_font_title = io.Fonts->AddFontFromFileTTF(segoe_bold, 24.0f);
    if (!g_font_regular)
        g_font_regular = io.Fonts->AddFontDefault();
    if (!g_font_bold)
        g_font_bold = g_font_regular;
    if (!g_font_title)
        g_font_title = g_font_regular;

    ApplyTheme();

    SportsApp app;
    app.Initialize();
    const std::string command_line = aegis::WideToUtf8(GetCommandLineW());
    if (HasCommandFlag(command_line, "--screenshot-smoke"))
        app.EnableScreenshotSmoke(CommandLineOption(command_line, "--smoke-view=", "dashboard"));

    ImVec4 clear_color = V4(0.018f, 0.035f, 0.031f, 1.0f);
    bool done = false;
    while (!done)
    {
        MSG msg;
        while (PeekMessage(&msg, nullptr, 0U, 0U, PM_REMOVE))
        {
            TranslateMessage(&msg);
            DispatchMessage(&msg);
            if (msg.message == WM_QUIT)
                done = true;
        }
        if (done)
            break;

        if (g_SwapChainOccluded && g_pSwapChain->Present(0, DXGI_PRESENT_TEST) == DXGI_STATUS_OCCLUDED)
        {
            Sleep(10);
            continue;
        }
        g_SwapChainOccluded = false;

        if (g_ResizeWidth != 0 && g_ResizeHeight != 0)
        {
            CleanupRenderTarget();
            g_pSwapChain->ResizeBuffers(0, g_ResizeWidth, g_ResizeHeight, DXGI_FORMAT_UNKNOWN, 0);
            g_ResizeWidth = g_ResizeHeight = 0;
            CreateRenderTarget();
        }

        ImGui_ImplDX11_NewFrame();
        ImGui_ImplWin32_NewFrame();
        ImGui::NewFrame();
        app.Render();
        ImGui::Render();

        const float clear_color_with_alpha[4] = { clear_color.x * clear_color.w, clear_color.y * clear_color.w, clear_color.z * clear_color.w, clear_color.w };
        g_pd3dDeviceContext->OMSetRenderTargets(1, &g_mainRenderTargetView, nullptr);
        g_pd3dDeviceContext->ClearRenderTargetView(g_mainRenderTargetView, clear_color_with_alpha);
        ImGui_ImplDX11_RenderDrawData(ImGui::GetDrawData());

        const HRESULT hr = g_pSwapChain->Present(1, 0);
        g_SwapChainOccluded = (hr == DXGI_STATUS_OCCLUDED);
    }

    ImGui_ImplDX11_Shutdown();
    ImGui_ImplWin32_Shutdown();
    ImGui::DestroyContext();
    CleanupDeviceD3D();
    DestroyWindow(hwnd);
    g_AppHwnd = nullptr;
    UnregisterClassW(wc.lpszClassName, wc.hInstance);
    if (app_icon)
        DestroyIcon(app_icon);
    if (app_icon_small)
        DestroyIcon(app_icon_small);
    return 0;
}

extern IMGUI_IMPL_API LRESULT ImGui_ImplWin32_WndProcHandler(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam);

namespace
{
bool CreateDeviceD3D(HWND hWnd)
{
    DXGI_SWAP_CHAIN_DESC sd{};
    sd.BufferCount = 2;
    sd.BufferDesc.Width = 0;
    sd.BufferDesc.Height = 0;
    sd.BufferDesc.Format = DXGI_FORMAT_R8G8B8A8_UNORM;
    sd.BufferDesc.RefreshRate.Numerator = 60;
    sd.BufferDesc.RefreshRate.Denominator = 1;
    sd.Flags = DXGI_SWAP_CHAIN_FLAG_ALLOW_MODE_SWITCH;
    sd.BufferUsage = DXGI_USAGE_RENDER_TARGET_OUTPUT;
    sd.OutputWindow = hWnd;
    sd.SampleDesc.Count = 1;
    sd.SampleDesc.Quality = 0;
    sd.Windowed = TRUE;
    sd.SwapEffect = DXGI_SWAP_EFFECT_DISCARD;

    UINT createDeviceFlags = 0;
    D3D_FEATURE_LEVEL featureLevel;
    const D3D_FEATURE_LEVEL featureLevelArray[2] = { D3D_FEATURE_LEVEL_11_0, D3D_FEATURE_LEVEL_10_0 };
    HRESULT res = D3D11CreateDeviceAndSwapChain(nullptr, D3D_DRIVER_TYPE_HARDWARE, nullptr, createDeviceFlags, featureLevelArray, 2, D3D11_SDK_VERSION, &sd, &g_pSwapChain, &g_pd3dDevice, &featureLevel, &g_pd3dDeviceContext);
    if (res == DXGI_ERROR_UNSUPPORTED)
        res = D3D11CreateDeviceAndSwapChain(nullptr, D3D_DRIVER_TYPE_WARP, nullptr, createDeviceFlags, featureLevelArray, 2, D3D11_SDK_VERSION, &sd, &g_pSwapChain, &g_pd3dDevice, &featureLevel, &g_pd3dDeviceContext);
    if (res != S_OK)
        return false;
    CreateRenderTarget();
    return true;
}

void CleanupDeviceD3D()
{
    CleanupRenderTarget();
    if (g_pSwapChain) { g_pSwapChain->Release(); g_pSwapChain = nullptr; }
    if (g_pd3dDeviceContext) { g_pd3dDeviceContext->Release(); g_pd3dDeviceContext = nullptr; }
    if (g_pd3dDevice) { g_pd3dDevice->Release(); g_pd3dDevice = nullptr; }
}

void CreateRenderTarget()
{
    ID3D11Texture2D* pBackBuffer = nullptr;
    g_pSwapChain->GetBuffer(0, IID_PPV_ARGS(&pBackBuffer));
    g_pd3dDevice->CreateRenderTargetView(pBackBuffer, nullptr, &g_mainRenderTargetView);
    pBackBuffer->Release();
}

void CleanupRenderTarget()
{
    if (g_mainRenderTargetView)
    {
        g_mainRenderTargetView->Release();
        g_mainRenderTargetView = nullptr;
    }
}

LRESULT WINAPI WndProc(HWND hWnd, UINT msg, WPARAM wParam, LPARAM lParam)
{
    if (ImGui_ImplWin32_WndProcHandler(hWnd, msg, wParam, lParam))
        return true;

    switch (msg)
    {
    case WM_NCHITTEST:
    {
        POINT pt{ GET_X_LPARAM(lParam), GET_Y_LPARAM(lParam) };
        RECT rc{};
        GetWindowRect(hWnd, &rc);
        const int edge = MulDiv(8, static_cast<int>(GetDpiForWindow(hWnd)), 96);
        const bool left = pt.x >= rc.left && pt.x < rc.left + edge;
        const bool right = pt.x <= rc.right && pt.x > rc.right - edge;
        const bool top = pt.y >= rc.top && pt.y < rc.top + edge;
        const bool bottom = pt.y <= rc.bottom && pt.y > rc.bottom - edge;
        if (top && left)
            return HTTOPLEFT;
        if (top && right)
            return HTTOPRIGHT;
        if (bottom && left)
            return HTBOTTOMLEFT;
        if (bottom && right)
            return HTBOTTOMRIGHT;
        if (left)
            return HTLEFT;
        if (right)
            return HTRIGHT;
        if (top)
            return HTTOP;
        if (bottom)
            return HTBOTTOM;
        return HTCLIENT;
    }
    case WM_GETMINMAXINFO:
    {
        MINMAXINFO* info = reinterpret_cast<MINMAXINFO*>(lParam);
        info->ptMinTrackSize.x = kMinWindowWidth;
        info->ptMinTrackSize.y = kMinWindowHeight;
        return 0;
    }
    case WM_SIZE:
        if (wParam == SIZE_MINIMIZED)
            return 0;
        g_ResizeWidth = static_cast<UINT>(LOWORD(lParam));
        g_ResizeHeight = static_cast<UINT>(HIWORD(lParam));
        return 0;
    case WM_SYSCOMMAND:
        if ((wParam & 0xfff0) == SC_KEYMENU)
            return 0;
        break;
    case WM_DESTROY:
        PostQuitMessage(0);
        return 0;
    default:
        break;
    }
    return DefWindowProcW(hWnd, msg, wParam, lParam);
}
}
