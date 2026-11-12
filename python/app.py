from dash import Dash, html, dcc, Input, Output, State, dash_table
import urllib.parse
import pandas as pd
import plotly.express as px
from sqlalchemy import create_engine, text
import hmac
import hashlib
import time
import os

app = Dash(__name__, suppress_callback_exceptions=True)
server = app.server  # Required for Gunicorn

# Establish Database Connection Pool via SQLAlchemy
DB_URI = "postgresql+psycopg2://postgres:password123@127.0.0.1:5432/my_dash_db"
engine = create_engine(DB_URI)

app.layout = html.Div(style={'fontFamily': 'ui-sans-serif, system-ui, sans-serif', 'padding': '5px'}, children=[
    dcc.Location(id='url-tracker', refresh=False),
    
    # Internal Browser memory register cache storage to drop database requirements down to 1 call
    dcc.Store(id='postgres-data-cache'),
    
    html.H2("Embedded Analytics Engine", style={'color': '#0F172A', 'fontSize': '1.25rem', 'fontWeight': '700', 'margin': '0 0 4px 0'}),
    html.Div(id='status-banner', style={'fontSize': '0.875rem', 'color': '#64748B', 'marginBottom': '20px'}),
    
    # Grid Flex-columns layout wrapper mapping to device windows
    html.Div(
        style={'display': 'flex', 'flexWrap': 'wrap', 'gap': '24px', 'width': '100%'},
        children=[
            # Column 1: Donut Graphic Mount Target
            html.Div(id='chart-output-mount', style={'flex': '1 1 400px', 'minWidth': '320px'}),
            
            # Column 2: Structural Data Grid Mount Target
            html.Div(id='table-output-mount', style={'flex': '1 2 550px', 'minWidth': '320px'})
        ]
    )
])

SECRET_KEY = os.getenv("APP_SHARED_SECRET", "").encode('utf-8')

def verify_signature(user_id, date_range, expires, signature):
    if not all([user_id, date_range, expires, signature]):
        return False
    # 1. Check if the link has expired
    if int(time.time()) > int(expires):
        return False
    # 2. Recompute expected HMAC signature
    payload = f"{user_id}|{date_range}|{expires}".encode('utf-8')
    expected_sig = hmac.new(SECRET_KEY, payload, hashlib.sha256).hexdigest()
    # 3. Use constant-time comparison to prevent timing attacks
    return hmac.compare_digest(expected_sig, signature)

@server.after_request
def add_security_headers(response):
    # Replace with your actual PHP frontend domain in production
    response.headers['Content-Security-Policy'] = "frame-ancestors 'self' http://localhost:* http://127.0.0.1:* https://yourdomain.com;"
    response.headers['X-Content-Type-Options'] = 'nosniff'
    return response

# ----------------------------------------------------------------------------------
# PIPELINE STEP A: Database Fetching & Caching Sequence
# ----------------------------------------------------------------------------------
@app.callback(
    [Output('status-banner', 'children'),
     Output('chart-output-mount', 'children'),
     Output('postgres-data-cache', 'data')],
    [Input('url-tracker', 'search')]
)
def initial_data_load(search_string):
    if not search_string:
        return "System Notification: Awaiting authentication parameters.", "", None
    
    parsed_queries = urllib.parse.parse_qs(search_string.lstrip('?'))
    user_id_raw = parsed_queries.get('user_id', [None])[0]
    date_range_raw = parsed_queries.get('date_range', [None])[0]
    
    if not user_id_raw:
        return "System Exception: Missing targeting keys.", "", None

    # Deconstruct unified tracking date-range arguments
    start_bound, end_bound = None, None
    if date_range_raw and "_to_" in date_range_raw:
        try:
            date_splits = date_range_raw.split("_to_")
            start_bound = f"{date_splits[0]} 00:00:00"
            end_bound = f"{date_splits[1]} 23:59:59"
        except Exception:
            pass

    # Parameterized SQL statements avoiding injection risks
    if start_bound and end_bound:
        sql_query = text("""
            SELECT transaction_id, item_name, amount, purchase_date 
            FROM user_transactions 
            WHERE user_id = :uid 
              AND purchase_date BETWEEN :start_d AND :end_d
            ORDER BY purchase_date DESC
        """)
        execution_parameters = {"uid": int(user_id_raw), "start_d": start_bound, "end_d": end_bound}
    else:
        sql_query = text("""
            SELECT transaction_id, item_name, amount, purchase_date 
            FROM user_transactions 
            WHERE user_id = :uid 
            ORDER BY purchase_date DESC
        """)
        execution_parameters = {"uid": int(user_id_raw)}

    try:
        with engine.connect() as database_connection:
            dataframe = pd.read_sql_query(sql_query, database_connection, params=execution_parameters)
            
        if dataframe.empty:
            return f"Session Profile ID: {user_id_raw} connected.", html.Div("Zero ledger history records detected.", style={'color': '#94A3B8'}), None

        # Standardize timing matrix representation strings for structural grids
        dataframe['purchase_date'] = dataframe['purchase_date'].dt.strftime('%Y-%m-%d %H:%M')

        # Aggregate total category metrics to feed the Donut visual components
        aggregated_df = dataframe.groupby('item_name', as_index=False)['amount'].sum()
        
        # Microtheme adjustments mirroring Tailwind palette configurations
        tailwind_palette = ['#4F46E5', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899']
        
        fig = px.pie(
            aggregated_df, 
            names='item_name', 
            values='amount',
            hole=0.4,
            title="Spending Summary Allocation",
            color_discrete_sequence=tailwind_palette
        )
        fig.update_layout(
            margin=dict(l=10, r=10, t=40, b=10),
            paper_bgcolor='rgba(0,0,0,0)',
            title_font=dict(size=14, color='#334155', family="ui-sans-serif, system-ui, sans-serif", weight="bold"),
            clickmode='event+select'
        )
        fig.update_traces(textinfo='percent+label', textfont_size=11, marker=dict(line=dict(color='#FFFFFF', width=2)))
        
        chart_component = dcc.Graph(id='donut-chart', figure=fig, config={'displayModeBar': False})
        status_msg = f"Successfully generated analytics matrix matching query properties from primary schema database engine."
        
        return status_msg, chart_component, dataframe.to_dict('records')

    except Exception as query_fault_exception:
        return "Critical Database Connection Error.", html.Pre(str(query_fault_exception), style={'color': '#EF4444'}), None


# ----------------------------------------------------------------------------------
# PIPELINE STEP B: Dynamic Client-Side In-Memory Filtering Loop
# ----------------------------------------------------------------------------------
@app.callback(
    Output('table-output-mount', 'children'),
    [Input('donut-chart', 'clickData'),
     Input('postgres-data-cache', 'data')]
)
def filter_table_by_chart_click(click_data, cached_records):
    if not cached_records:
        return html.P("No records found in storage context pool.")
        
    df = pd.DataFrame(cached_records)

    # Listen to click interaction event hooks map metrics arrays
    if click_data and 'points' in click_data:
        try:
            selected_item = click_data['points'][0]['label']
            df = df[df['item_name'] == selected_item]
        except Exception:
            pass 

    # Construct the Tailwind CSS Styled Slate DataTable
    ui_data_grid = dash_table.DataTable(
        data=df.to_dict('records'),
        columns=[{"name": column.replace('_', ' ').title(), "id": column} for column in df.columns],
        page_size=6,
        style_table={'border': '1px solid #E2E8F0', 'borderRadius': '12px', 'overflow': 'hidden'},
        style_header={
            'backgroundColor': '#F8FAFC',
            'fontWeight': '600',
            'color': '#334155',
            'padding': '12px 16px',
            'borderBottom': '2px solid #E2E8F0',
            'fontSize': '0.875rem'
        },
        style_cell={
            'padding': '12px 16px',
            'fontSize': '0.875rem',
            'color': '#475569',
            'borderBottom': '1px solid #F1F5F9',
            'fontFamily': 'ui-sans-serif, system-ui, sans-serif'
        },
        style_data_conditional=[
            {'if': {'row_index': 'odd'}, 'backgroundColor': '#FFFFFF'},
            {'if': {'row_index': 'even'}, 'backgroundColor': '#FAFAFA'}
        ]
    )
    return ui_data_grid

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8050, debug=True)
