let courses = JSON.parse(document.getElementById('reports_courses_data').dataset.courses);
let exam_questions = JSON.parse(document.getElementById('reports_questions_data').dataset.questions);
let exam_students = JSON.parse(document.getElementById('reports_students_data').dataset.students);
let max_score_range = JSON.parse(document.getElementById('reports_max_score_range_data').dataset.maxScoreRange);
let with_groups = courses.length > 1 ? true : false; 
let subplot_rows = with_groups ? 2 : 1;

let exam_histogram_boxplot_data = JSON.parse(document.getElementById('reports_exam_histogram_boxplot_data').dataset.examScores);
let normalized_exam_scores_by_subjects = JSON.parse(document.getElementById('reports_normalized_exam_scores_by_subjects_data').dataset.subjectScores);
let normalized_exam_scores_by_topics = JSON.parse(document.getElementById('reports_normalized_exam_scores_by_topics_data').dataset.topicScores);
let exam_question_heatstrip_data = JSON.parse(document.getElementById('reports_exam_question_heatstrip_data').dataset.levelScores);
let exam_compare_types_and_blooms_data = JSON.parse(document.getElementById('reports_type_with_level_scores_data').dataset.typeScores);

const group_colors = ['rgb(49,130,189)', 'rgb(87, 196, 133)', 'rgb(238, 99, 99)', 'rgb(120,130,80)'];
const bloom_colors = {
    'Remember': 'rgb(49,130,189)',
    'Understand': 'rgb(87, 196, 133)',
    'Apply': 'rgb(252, 186, 3)',
    'Analyze': 'rgb(245, 175, 75)',
    'Evaluate' : 'rgb(238, 99, 99)',
    'Create' : 'rgb(158, 120, 242)'
};
const BLOOM_ORDER = ["Create", "Evaluate", "Analyze", "Apply", "Understand", "Remember"];
const MAX_RANK = BLOOM_ORDER.length; 
const bloom_ranks = {};
BLOOM_ORDER.reverse().forEach((level, index) => {
    bloom_ranks[level] = index + 1; 
});
// to fix reverse()
BLOOM_ORDER.reverse();
const hover_label = {
                        font: {
                        family: 'Arial',
                        size: 14,
                        color: '#1f2937' 
                        },
                        bgcolor: 'white', 
                        bordercolor: '#1f2937', 
                        borderwidth: 2 
                    }


const groupByData = (data, data_column) => {
    if (!Array.isArray(data)) {
        console.error("Input must be an array.");
        return {};
    }

    return data.reduce((accumulator, item) => {
        const key = item[data_column];
        
        accumulator[key] = accumulator[key] || []; 
        
        accumulator[key].push(item);
        
        return accumulator;
    }, {});
};

function renderHistogram(data){
    let histogram_traces = [];
    let histogram_hover_template =
        '<b>Bin Midpoint</b> : %{x}<br>' +
        '<b>Count</b> : %{y}<br>' + 
        '<extra></extra>'
    
        
    if (with_groups){
        let overall_histogram_trace = {
            x: data.map(item => item.total_score),
            name: 'Overall',  
            type: 'histogram',
            xbins: { size: 5 },
            hovertemplate: histogram_hover_template,
            hoverinfo: 'text',
            marker: {
            line: { width: 2, color: '#333'},
            color: 'rgb(49,130,189)',
            }
        };
        histogram_traces.push(overall_histogram_trace);
    }

    const grouped_by_course = groupByData(data, 'course_abbreviation');
    const groups = Object.keys(grouped_by_course);

    groups.forEach((group, idx) => {
        let trace = {
            x: grouped_by_course[group].map(item => item.total_score),
            type: 'histogram',
            name: group,  
            xbins: { size: 5 },
            xaxis: with_groups == false ? 'x1' : 'x2' ,
            yaxis: with_groups == false ? 'y1' : 'y2',
            hovertemplate: histogram_hover_template,
            hoverinfo: 'text',
            hoverlabel: {
                namelength: -1 
            },
            marker: {
                line: { width: 2, color: '#333' },
                color: group_colors[idx % group_colors.length],  
                opacity: .7
            }
        };
        histogram_traces.push(trace);
    });

    let histogram_layout = {
      grid: {rows:subplot_rows, columns:1, pattern: 'independent'},
      title: {
        text: "Exam Scores Distribution",
        font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
        x: 0.55,
        xanchor: 'center',
        yanchor: 'top'
      },
      barmode: "stack",
      height: 500,
      autosize: true,
      margin: { l: 40, r: 10, t: 80, b: 30 },
      xaxis: {
        range: [0, max_score_range],
        dtick: 5,
        tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        title: { text: with_groups == false ? 'Exam Scores' : '', font: { size: 12, color: '#7f7f7f' },  standoff: 10 },
        tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
      },
      yaxis: {
        dtick: 1,
        domain: [0.55, 1],
        title: { text: 'Frequency', font: { size: 12, color: '#7f7f7f' } },
        tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
      },
      xaxis2: {
        range: [0, max_score_range],
        dtick: 5,
        title: { text: 'Exam Scores', font: { size: 12, color: '#7f7f7f' },  standoff: 10 },
        tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        anchor: 'y2'
      },
      yaxis2: {
        dtick: 1,
        domain: [0, 0.50],
        title: { text: 'Frequency', font: { size: 12, color: '#7f7f7f' } },
        tickfont: { size: 12, color: 'rgb(107, 107, 107)' }
      },
    legend: {
        orientation: 'h',
        x: 0.5,
        xanchor: 'center',
        y: 1.0,      
        yanchor: 'bottom'
    }
    };

    Plotly.newPlot('exam-histogram', histogram_traces, histogram_layout).then(() => {
            Plotly.Plots.resize('exam-histogram');  
        });
}

function renderBoxPlot(data){
    let boxplot_traces = [];
    let boxplot_hovertemplate = '<b>User ID</b>: %{customdata[0]}<br>' +
                                '<b>Points</b>: %{y}<br>' +
                                '<extra></extra>'
    if (with_groups){
        const overall_boxplot_hover_texts = data.map(item => [item.user_id]);
        let overall_boxplot_trace = {
            y: data.map(item => item.total_score),
            name: 'Overall',  
            type: 'box',
            boxpoints: 'all',
            customdata: overall_boxplot_hover_texts,
            hovertemplate: boxplot_hovertemplate,
            jitter: 0.8,
            whiskerwidth: 0.4,
            pointpos: -2,
            xaxis: 'x1',
            yaxis: 'y1',
            marker: {
                color: 'rgb(49,130,189)',
                outliercolor: 'rgba(219, 64, 82, 0.6)',
                size: 5,
                line: { width: 1, color: '#333', outliercolor: 'rgba(219, 64, 82, 1.0)', outlierwidth: 2},
                opacity: 0.7
            },    
            };
        boxplot_traces.push(overall_boxplot_trace);        
    }

    const grouped_by_course = groupByData(data, 'course_abbreviation');

    const groups = Object.keys(grouped_by_course);

    groups.forEach((group, idx) => {
        const groups_boxplot_hover_texts = grouped_by_course[group].map(item => [item.user_id]);
        let trace = {
            y: grouped_by_course[group].map(item => item.total_score),
            type: 'box',
            name: group,  
            boxpoints: 'all',
            customdata: groups_boxplot_hover_texts,
            hovertemplate: boxplot_hovertemplate,
            jitter: 0.8,
            whiskerwidth: 0.4,
            pointpos: -2,
            xaxis: with_groups == false ? 'x1' : 'x2',
            yaxis: with_groups == false ? 'y1' : 'y2',
            marker: {
                size: 5,
                line: { width: 1, color: '#333', outliercolor: 'rgba(219, 64, 82, 1.0)', outlierwidth: 2},
                outliercolor: 'rgba(219, 64, 82, 0.6)',
                color: group_colors[idx % group_colors.length],  
            },
            opacity: 1
        };
        boxplot_traces.push(trace);
    });

    let boxplot_layout = {
        grid: {rows:subplot_rows, columns:1, pattern: 'independent'},
        title: {
            text: "Exam Scores Box Plots",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.55,
            xanchor: 'center',
            yanchor: 'top'  
        },
        height: 500,
        autosize: true,
        margin: { l: 60, r: 10, t: 80, b: 30 },
        yaxis: {
            autorange: true,
            dtick: 10,
            gridwidth: 1,
            title: { text: 'Exam Scores', font: { size: 12, color: '#7f7f7f' }},
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        },
        xaxis: {
            domain: [0, 1],
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        },
        yaxis2: {
            title: { text: 'Exam Scores', font: { size: 12, color: '#7f7f7f' }},
            domain: [0, 0.50],
            autorange: true,
            dtick: 10,
            gridwidth: 1,
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
            anchor: 'y2'
        },
        xaxis2: {
            domain: [0, 1],
            type: 'category',
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' }
        },
        legend: {
            orientation: 'h',
            x: 0.5,
            xanchor: 'center',
            y: 1.0,          
            yanchor: 'bottom'
        }
    };

    Plotly.newPlot('exam-boxplot', boxplot_traces, boxplot_layout).then(() => {
            Plotly.Plots.resize('exam-boxplot'); 
        });

}

function renderSubjectHeatmap(data){
    const groups = Object.keys(data);
    const subjects = Object.keys(data[groups[0]]); 
      const normalized_scores = subjects.map(subject => {
        return groups.map(course => {
            return Number(data[course][subject]); 
        });
    });

    let subject_heatmap_hovertemplate = '<b>Course</b>: %{x}<br>' +
                            '<b>Subject</b>: %{y}<br>' +
                            '<b>Normalized Score</b>: %{z}%<br>' +
                            '<extra></extra>'

    const heatmap_colorscale = [
        [0.0, 'rgb(255,255,255)'],
        [1.0, 'rgb(49,130,189)']
    ];

    const subject_heatmap_trace = [{
        z: normalized_scores,
        x: groups,
        y: subjects,
        type: 'heatmap',
        colorscale: heatmap_colorscale,
        hovertemplate: subject_heatmap_hovertemplate,
        zmin: 0,
        zmax: 100,
        xgap: 1,
        ygap: 1,
        colorbar: {
            title: 'Accuracy Score',
            tickformat: '.0f',
            ticksuffix: '%'
        },
        hoverlabel: hover_label
    }];

    const subject_heatmap_layout = {
        title: {
            text: "Performance Distribution by Subjects",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.5,
            xanchor: 'center'
        },
        height: 500,
        autosize: true,
        margin: { l: 180, r: 20, t: 60, b: 80 },
        xaxis: { title: 'Student Groups' },
        yaxis: { title: 'Subjects', automargin: true },
    };

    Plotly.newPlot('exam-subjects', subject_heatmap_trace, subject_heatmap_layout);
}

function renderTopicHeatmap(data){
    const groups = Object.keys(data);
    const topics = Object.keys(data[groups[0]]); 
    const normalized_scores = topics.map(topic => {
        return groups.map(course => {
            return Number(data[course][topic]); 
        });
    });

    let topic_heatmap_hovertemplate = '<b>Course</b> : %{x}<br>' +
                        '<b>Topic</b>: %{y}<br>' +
                        '<b>Normalized Score</b>: %{z}%<br>' +
                        '<extra></extra>'

    const heatmap_colorscale = [
        [0.0, 'rgb(255,255,255)'],    
        [1.0, 'rgb(87, 196, 133)']    
    ];

    const topic_heatmap_trace = [{
        z: normalized_scores,
        x: groups,
        y: topics,
        type: 'heatmap',
        colorscale: heatmap_colorscale,
        hovertemplate: topic_heatmap_hovertemplate,
        zmin: 0,
        zmax: 100,
        xgap: 1,
        ygap: 1,
        colorbar: {
            title: 'Accuracy Score',
            tickformat: '.0f', 
            ticksuffix: '%'
        },
        hoverlabel: hover_label
    }];

    const topic_heatmap_layout = {
        title: {
            text: "Performance Distribution by Topics",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.5,
            xanchor: 'center'
        },
        height: 500,
        autosize: true,
        margin: { l: 180, r: 20, t: 60, b: 80 },
        xaxis: { title: 'Student Groups' },
        yaxis: { title: 'Topics', automargin: true },
    };

    Plotly.newPlot('exam-topics', topic_heatmap_trace, topic_heatmap_layout);

}

function renderBarChartCompareTypesWithLevels(data){
    const exam_compare_types_and_blooms_traces = [];
    const exam_blooms_distribution_traces = [];
    const course_groups = Object.keys(data);

    course_groups.forEach(courseName => {
        const question_types = data[courseName]; 

        Object.keys(question_types).forEach(qtype => {
            const qtypeData = question_types[qtype];
        
            Object.keys(qtypeData.blooms).forEach(bloom_level => {
                
                const bloom_metrics = qtypeData.blooms[bloom_level];

                const raw_score = bloom_metrics.aggregated_raw_score;
                const accuracy = bloom_metrics.accuracy_percentage;
                const contribution = bloom_metrics.contribution_percentage;
                
                const x_value = `${courseName}<br>${qtypeData.code}`; 
                
                const compare_type_hovertemplate = `
<extra></extra>
<b>${courseName} - ${qtypeData.code}</b><br><br>
<i>${bloom_level}</i><br>
Aggregated Raw Score: ${raw_score.toFixed(0)} points<br>
Accuracy: ${accuracy.toFixed(2)}%<br>
Contribution: ${contribution.toFixed(2)}%`;


                exam_compare_types_and_blooms_traces.push({
                    x: [x_value],
                    y: [raw_score],
                    name: bloom_level, 
                    type: 'bar',
                    marker: {
                        color: bloom_colors[bloom_level] || '#888',
                        line: { width: 2, color: '#333' },
                        opacity: 0.7
                    },
                    hovertemplate: compare_type_hovertemplate,
                    legendrank: bloom_ranks[bloom_level] || 1000, 
                    legendgroup: bloom_level,
                    showlegend: !exam_compare_types_and_blooms_traces.some(t => t.name === bloom_level),
                    hoverlabel: hover_label
                });

                exam_blooms_distribution_traces.push({
                    x: [x_value],
                    y: [contribution],
                    name: bloom_level, 
                    type: 'bar',
                    marker: {
                        color: bloom_colors[bloom_level] || '#888',
                        line: { width: 2, color: '#333' },
                        opacity: 0.7
                    },
                    ymin: 0,
                    ymax: 100,
                    hovertemplate: compare_type_hovertemplate,
                    legendrank: bloom_ranks[bloom_level] || 1000, 
                    legendgroup: bloom_level,
                    showlegend: !exam_blooms_distribution_traces.some(t => t.name === bloom_level),
                    hoverlabel: hover_label
                });
            });
        });
    });

    console.log(exam_compare_types_and_blooms_traces);

    const exam_compare_types_and_blooms_layout = {
        title: {
            text: "Compare by Question Types with Bloom's Level",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.50,
            xanchor: 'center',
            yanchor: 'top'
        },
        height: 500,
        autosize: true,
        barmode: 'stack',
        margin: { l: 60, r: 10, t: 80, b: 80 },
        hovermode: 'closest',
        yaxis: {
            gridwidth: 1,
            title: { text: 'Aggregated Raw Exam Scores', font: { size: 12, color: '#7f7f7f' }},
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        },
        xaxis: {
            domain: [0, 1],
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        },
        legend: {
            orientation: 'h',
            x: 0.5,
            xanchor: 'center',
            y: 1.0,         
            yanchor: 'bottom'
        }
    };

    const exam_blooms_distribution_layout = {
        title: {
            text: "Contribution of Bloom levels in each question types",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.50,
            xanchor: 'center',
            yanchor: 'top'
        },
        height: 500,
        autosize: true,
        barmode: 'stack',
        margin: { l: 60, r: 10, t: 80, b: 80 },
        hovermode: 'closest',
        yaxis: {
            gridwidth: 1,
            title: { text: 'Contribution Percentage', font: { size: 12, color: '#7f7f7f' }},
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
            tickformat: '.1f', 
            ticksuffix: '%'
        },
        xaxis: {
            domain: [0, 1],
            tickfont: { size: 12, color: 'rgb(107, 107, 107)' },
        }, 
        legend: {
            orientation: 'h',
            x: 0.5,
            xanchor: 'center',
            y: 1.0,         
            yanchor: 'bottom'
        }
    };

    Plotly.newPlot('exam-compare-group', exam_compare_types_and_blooms_traces, exam_compare_types_and_blooms_layout).then(() => {
            Plotly.Plots.resize('exam-compare-group'); 
        });

    
    Plotly.newPlot('exam-type-group-normalize', exam_blooms_distribution_traces, exam_blooms_distribution_layout).then(() => {
            Plotly.Plots.resize('exam-type-group-normalize'); 
        });
}

function renderHeatStripQuestions(data){
    const grouped_by_levels = groupByData(data, 'question_level');

    var question_heatstrip_traces = Object.keys(grouped_by_levels).map(level => {
        let qData = grouped_by_levels[level].sort((a, b) => a.accuracy_percentage - b.accuracy_percentage);
        return {
            y: Array(qData.length).fill(level),
            x: Array(data.length).fill(1),
            type: 'bar',
            orientation: 'h',
            marker: {
                line: { width: 2.1, color: '#333', opacity:0.7},
                color: qData.map(q => q.accuracy_percentage),
                colorscale: [
                    ['0.0', 'rgb(49, 130, 189)'],
                    ['0.1', 'rgb(69, 135, 179)'],
                    ['0.2', 'rgb(89, 140, 169)'],
                    ['0.3', 'rgb(109, 145, 159)'],
                    ['0.4', 'rgb(129, 150, 149)'],
                    ['0.5', 'rgb(149, 155, 139)'],
                    ['0.6', 'rgb(169, 160, 129)'],
                    ['0.7', 'rgb(189, 165, 119)'],
                    ['0.8', 'rgb(209, 170, 109)'],
                    ['0.9', 'rgb(229, 175, 99)'],
                    ['1.0', 'rgb(245, 175, 75)']
                ],
                opacity: 1,
                cmin: 0,
                cmax: 100,
                colorbar: { 
                    title: 'Bloom Score %',   
                    tickformat: '.0f', 
                    ticksuffix: '%'
                }
            },
            customdata: qData.map(q => [q.question_name, q.question_type, q.average_score, q.accuracy_percentage, q.maximum_points_attainable, q.topic_name, q.subject_name]),
            hovertemplate:
                "Question text: %{customdata[0]}<br>" +
                "Question type: %{customdata[1]}<br>" +
                "Average score: %{customdata[2]}<br>" +
                "Average score in Percent: %{customdata[3]}%<br>" +
                "Max Score Attainable: %{customdata[4]}<br>"  +
                "Question topic: %{customdata[5]}<br>" +
                "Question subject: %{customdata[6]}<extra></extra>",
            hoverlabel: {
                font: { color: 'white' }      // hover text color
            },
            showlegend: false
            };
    });

     var answer_time_heatstrip_traces = Object.keys(grouped_by_levels).map(level => {
        let qData = grouped_by_levels[level].sort((a, b) => a.average_time_to_answer - b.average_time_to_answer);
        return {
            y: Array(qData.length).fill(level),
            x: Array(data.length).fill(1),
            type: 'bar',
            orientation: 'h',
            marker: {
                line: { width: 2.1, color: '#333', opacity:0.7},
                color: qData.map(q => q.average_time_to_answer),
                colorscale: [
                    ['0.0', 'rgb(49, 130, 189)'],
                    ['0.1', 'rgb(69, 135, 179)'],
                    ['0.2', 'rgb(89, 140, 169)'],
                    ['0.3', 'rgb(109, 145, 159)'],
                    ['0.4', 'rgb(129, 150, 149)'],
                    ['0.5', 'rgb(149, 155, 139)'],
                    ['0.6', 'rgb(169, 160, 129)'],
                    ['0.7', 'rgb(189, 165, 119)'],
                    ['0.8', 'rgb(209, 170, 109)'],
                    ['0.9', 'rgb(229, 175, 99)'],
                    ['1.0', 'rgb(245, 175, 75)']
                ],
                opacity: 1,
                cmin: 0,
                cmax: 25,
                colorbar: { 
                    title: 'Answer Time',
                    tickformat: '.0f', 
                    ticksuffix: 's' 
                }
            },
            customdata: qData.map(q => [q.question_name, q.question_type, q.average_score, q.accuracy_percentage, q.maximum_points_attainable, q.topic_name, q.subject_name]),
            hovertemplate:
                "Question text: %{customdata[0]}<br>" +
                "Question type: %{customdata[1]}<br>" +
                "Average score: %{customdata[2]}<br>" +
                "Average score in Percent: %{customdata[3]}%<br>" +
                "Max Score Attainable: %{customdata[4]}<br>"  +
                "Question topic: %{customdata[5]}<br>" +
                "Question subject: %{customdata[6]}<extra></extra>",
            hoverlabel: {
                font: { color: 'white' }      // hover text color
            },
            showlegend: false
            };
    });

    var question_heatstrip_layout = {
    barmode: 'stack',
        title: {
        text: "Performance Distribution by Question Levels ",
        font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
        x: 0.5,
        xanchor: 'center'
    },
    width: 900,
    height: 400,
    xaxis: {  
        range: [0, 20],  
        dtick: 1,
        zeroline: false, 
        title: {
            text: 'No. of Questions',
            font: { size: 12, color: '#7f7f7f' }
            }
        },
    yaxis: { 
    tickfont: {
        size: 12,
        color: 'rgb(107, 107, 107)',
    },
    categoryorder: "array",
    categoryarray: BLOOM_ORDER,
    },
    autosize: true,
    margin: { l: 70, r: 20, t: 60, b: 100 },
    annotations: [
    {
        text: "Average Scores <br> in Percent",
        xref: "paper",
        yref: "paper",
        x: 1.07,       // align with colorbar (to the right of plot)
        y: 0,      // below the plot area
        showarrow: false,
        textangle: 0, // rotate text
        font: {
            size: 10,
            color: 'rgb(107, 107, 107)'
        },
        xanchor: "center",
        yanchor: "top"
    }
    ]
    };

    var answer_time_heatstrip_layout = {
        barmode: 'stack',
            title: {
            text: "Average Answering Time by Question Levels ",
            font: { size: 16, color: "#4B5563", family: "Arial Black, sans-serif" },
            x: 0.5,
            xanchor: 'center'
        },
        width: 900,
        height: 400,
        xaxis: {  
            range: [0, 20],
            dtick: 1,
            zeroline: false, 
            title: {
                text: 'No. of Questions',
                font: { size: 12, color: '#7f7f7f' }
                }
            },
        yaxis: { 
        tickfont: {
            size: 12,
            color: 'rgb(107, 107, 107)',
        },
        categoryorder: "array",
        categoryarray: BLOOM_ORDER,
        },
        autosize: true,
        margin: { l: 70, r: 20, t: 60, b: 100 },
        annotations: [
            {
                text: "Average <br> Answer Time",
                xref: "paper",
                yref: "paper",
                x: 1.07,       // align with colorbar (to the right of plot)
                y: 0,      // below the plot area
                showarrow: false,
                textangle: 0, // rotate text
                font: {
                    size: 10,
                    color: 'rgb(107, 107, 107)'
                },
                xanchor: "center",
                yanchor: "top"
            }
        ]
    };

    Plotly.newPlot('exam-heatstrip', question_heatstrip_traces, question_heatstrip_layout).then(() => {
            Plotly.Plots.resize('exam-heatstrip');  
        });

    Plotly.newPlot('exam-answer-time-heatstrip', answer_time_heatstrip_traces, answer_time_heatstrip_layout).then(() => {
        Plotly.Plots.resize('exam-answer-time-heatstrip');  
    });

}

document.addEventListener('DOMContentLoaded', () => {
    const histogram = document.getElementById('exam-histogram');
    const boxplot = document.getElementById('exam-boxplot');
    const subject_heatmap = document.getElementById('exam-subjects');
    const topic_heatmap = document.getElementById('exam-topics');
    const compare_type = document.getElementById('exam-compare-group');
    const heatstrip_questions = document.getElementById('exam-heatstrip');
    const heapstrip_answer_time = document.getElementById('exam-answer-time-heatstrip')

    if (histogram) {
        try {
            renderHistogram(exam_histogram_boxplot_data);
        } catch (error) {
            console.error("Histogram Graph Failed", error.message);
        } 
    }
    if (boxplot) {
        try {
            renderBoxPlot(exam_histogram_boxplot_data);
        } catch (error) {
            console.error("Boxplot Graph Failed", error.message);
        } 
    }
    if (subject_heatmap) {
        try {
            renderSubjectHeatmap(normalized_exam_scores_by_subjects);
        } catch (error) {
            console.error("Subject Heatmap Graph Failed", error.message);
        } 
    }
    if (topic_heatmap) {
        try {
            renderTopicHeatmap(normalized_exam_scores_by_topics);
        } catch (error) {
            console.error("Topic Heatmap Graph Failed", error.message);
        } 
    }
    if (compare_type) {
        try {
            renderBarChartCompareTypesWithLevels(exam_compare_types_and_blooms_data);
        } catch (error) {
            console.error("Compare Question Types Graph Failed", error.message);
        } 
    }
    if (heatstrip_questions && heapstrip_answer_time) {
        try {
            renderHeatStripQuestions(exam_question_heatstrip_data);
        } catch (error) {
            console.error("Question HeatStrip Graph Failed", error.message);
        } 
    }
});